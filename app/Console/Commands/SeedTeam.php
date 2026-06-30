<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\Position;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DummyTeamAttendanceSeeder as Seed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Guarantees the full 89-person roster shows "On Duty" on the ESS Team tab.
 * For each row in the master doc it MATCHES an existing active employee (by NIK,
 * then normalised name) and reuses it — or CREATES the person if missing — then
 * checks them in today. Existing real employees are never duplicated; only the
 * created people and the check-in rows are tagged, so dummy:teardown reverses it.
 */
class SeedTeam extends Command
{
    protected $signature = 'dummy:seed-team
        {--path= : Path to the employee master-data markdown (TSV block)}
        {--dry-run : Report matched/create/skip counts, write nothing}
        {--force : Skip confirmation}';

    protected $description = 'Match-or-create the 89-person roster from the master doc and check them all in today, so the Team tab shows 89 On Duty. No duplicates.';

    public function handle(): int
    {
        $rows = $this->parseMasterData();
        if (empty($rows)) {
            $this->error('No rows parsed. Provide the master doc via --path=/absolute/path/to/employee_master_data.md');

            return self::FAILURE;
        }

        $today = Carbon::today();
        $hireDate = Carbon::create(2025, 6, 1)->startOfDay();
        $dry = (bool) $this->option('dry-run');

        $plan = ['match' => 0, 'create' => 0, 'blank' => 0];
        foreach ($rows as $r) {
            if (trim($r['Nama Lengkap'] ?? '') === '') {
                $plan['blank']++;

                continue;
            }
            $this->matchExisting($r) ? $plan['match']++ : $plan['create']++;
        }

        $this->table(['Plan', 'Count'], [
            ['Rows in master doc', count($rows)],
            ['Match existing employee (reuse, no dup)', $plan['match']],
            ['Create new employee', $plan['create']],
            ['Skipped (blank name)', $plan['blank']],
            ['Projected On Duty after run', $plan['match'] + $plan['create']],
        ]);

        if ($dry) {
            $this->warn('Dry run — nothing written.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply the above for '.$today->toDateString().'?')) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $deptMap = $this->ensureDepartments();
        $usedNik = [];
        $checkedIn = 0;
        $idx = 0;

        foreach ($rows as $r) {
            $name = trim($r['Nama Lengkap'] ?? '');
            if ($name === '') {
                continue;
            }
            $jabatan = trim($r['Jabatan'] ?? '') ?: 'Staff';

            $employee = $this->matchExisting($r);

            if (! $employee) {
                $no = trim($r['No'] ?? '') !== '' ? trim($r['No']) : (string) $idx;
                $user = User::updateOrCreate(
                    ['email' => Seed::EMAIL_PREFIX.$no.'@'.Seed::EMAIL_DOMAIN],
                    ['name' => $name, 'password' => Hash::make(Str::random(40))]
                );

                $nik = preg_replace('/\D/', '', (string) ($r['Nomor KTP'] ?? '')) ?: null;
                if ($nik !== null && (isset($usedNik[$nik])
                    || Employee::where('nik', $nik)->where('user_id', '!=', $user->id)->exists())) {
                    $nik = null;
                }
                if ($nik !== null) {
                    $usedNik[$nik] = true;
                }

                $employee = Employee::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nik' => $nik,
                        'gender' => $this->mapGender($r['Jenis Kelamin'] ?? ''),
                        'phone' => trim($r['No HP'] ?? '') ?: null,
                        'hire_date' => $hireDate,
                        'employment_status' => 'permanent',
                        'is_active' => true,
                        'metadata' => ['seed_batch' => Seed::BATCH, 'source_no' => $no],
                    ]
                );
            }

            // Ensure a primary position only if the employee has none — never
            // overwrite a real employee's existing org assignment.
            if (! $employee->primaryPosition()->exists()) {
                EmployeePosition::create([
                    'employee_id' => $employee->id,
                    'position_id' => $this->ensurePosition($jabatan)->id,
                    'department_id' => $this->resolveDepartment($jabatan, $deptMap),
                    'start_date' => $hireDate,
                    'is_primary' => true,
                    'metadata' => ['seed_batch' => Seed::BATCH],
                ]);
            }

            $checkIn = $today->copy()->setTime(7, 0)->addMinutes(($idx * 7) % 110);
            $status = $checkIn->lte($today->copy()->setTime(8, 15))
                ? AttendanceLog::STATUS_PRESENT
                : AttendanceLog::STATUS_LATE;

            AttendanceLog::updateOrCreate(
                ['employee_id' => $employee->id, 'date' => $today->toDateString()],
                [
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'check_in_time' => $checkIn,
                    'check_out_time' => null,
                    'check_in_latitude' => -6.200000 + (($idx % 20) * 0.001),
                    'check_in_longitude' => 106.816666 + (($idx % 20) * 0.001),
                    'check_in_address' => 'Jakarta',
                    'source' => AttendanceLog::SOURCE_MOBILE,
                    'status' => $status,
                    'missing_checkout' => false,
                    'metadata' => ['seed_batch' => Seed::BATCH],
                ]
            );
            $checkedIn++;
            $idx++;
        }

        $this->info("Done — {$checkedIn} employees checked in for {$today->toDateString()}. Roll back: php artisan dummy:teardown");

        return self::SUCCESS;
    }

    protected function matchExisting(array $r): ?Employee
    {
        $nik = preg_replace('/\D/', '', (string) ($r['Nomor KTP'] ?? ''));
        if ($nik !== '') {
            $byNik = Employee::where('is_active', true)->where('nik', $nik)->first();
            if ($byNik) {
                return $byNik;
            }
        }

        $name = $this->normalizeName($r['Nama Lengkap'] ?? '');
        if ($name === '') {
            return null;
        }

        return Employee::where('is_active', true)
            ->whereHas('user', fn ($q) => $q->whereRaw('LOWER(TRIM(name)) = ?', [$name]))
            ->first();
    }

    protected function normalizeName(string $raw): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
    }

    protected function parseMasterData(): array
    {
        $path = $this->resolveDocPath();
        if (! $path) {
            return [];
        }

        $content = file_get_contents($path);
        if (! preg_match('/```tsv\s*\n(.*?)\n```/s', $content, $m)) {
            // Allow a raw TSV file too (no markdown fence).
            $body = $content;
        } else {
            $body = $m[1];
        }

        $lines = preg_split('/\r?\n/', trim($body));
        $headers = array_map('trim', explode("\t", array_shift($lines)));
        if (! in_array('Nama Lengkap', $headers, true)) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_pad(explode("\t", $line), count($headers), '');
            $rows[] = array_combine($headers, array_slice($cells, 0, count($headers)));
        }

        return $rows;
    }

    protected function resolveDocPath(): ?string
    {
        $candidates = array_filter([
            $this->option('path'),
            storage_path('app/employee_master_data.md'),
            base_path('database/data/employee_master_data.md'),
            base_path('../docs/employee_master_data_ai_agent.md'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function ensureDepartments(): array
    {
        foreach ([['HR', 'Human Resources'], ['ENG', 'Engineering'], ['FIN', 'Finance'], ['MKT', 'Marketing']] as [$code, $name]) {
            Department::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        return Department::whereIn('code', ['HR', 'ENG', 'FIN', 'MKT'])->pluck('id', 'code')->all();
    }

    protected function resolveDepartment(string $jabatan, array $map): ?int
    {
        $j = strtolower($jabatan);
        foreach (['it ', 'engineer', 'software', 'sofware', 'system', 'field', 'development', 'dev '] as $k) {
            if (str_contains($j, $k)) {
                return $map['ENG'] ?? null;
            }
        }
        foreach (['marketing', 'sales', 'social media', 'content', 'design', 'digital', 'photo', 'videograph', 'business development', 'customer service'] as $k) {
            if (str_contains($j, $k)) {
                return $map['MKT'] ?? null;
            }
        }
        foreach (['hr ', 'recruit', 'training', 'legal'] as $k) {
            if (str_contains($j, $k)) {
                return $map['HR'] ?? null;
            }
        }

        return $map['FIN'] ?? null;
    }

    protected function ensurePosition(string $title): Position
    {
        $title = trim($title);
        $code = substr(Seed::POSITION_CODE_PREFIX.strtoupper(Str::slug($title, '_')), 0, 60);

        return Position::firstOrCreate(['code' => $code], ['title' => $title]);
    }

    protected function mapGender(string $raw): ?string
    {
        $g = strtolower(str_replace([' ', '-'], '', $raw));
        if (str_contains($g, 'perempuan')) {
            return 'female';
        }
        if (str_contains($g, 'laki')) {
            return 'male';
        }

        return null;
    }
}
