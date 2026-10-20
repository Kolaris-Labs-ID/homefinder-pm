<?php

namespace Database\Seeders;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds demo employees from docs/employee_master_data_ai_agent.md so the ESS
 * Team tab shows them "On Duty" today. Every record is tagged for clean rollback
 * via `php artisan dummy:teardown`. PII is written to the DB only; never logged.
 */
class DummyTeamAttendanceSeeder extends Seeder
{
    public const BATCH = 'dummy_team_v1';

    public const EMAIL_PREFIX = 'dummyteam+';

    public const EMAIL_DOMAIN = 'homefinder.local';

    public const POSITION_CODE_PREFIX = 'DMY_';

    public function run(): void
    {
        $rows = $this->parseMasterData();

        if (empty($rows)) {
            $this->command?->error('No employee rows parsed from master data doc — aborting.');

            return;
        }

        $today = Carbon::today();
        $hireDate = Carbon::create(2025, 6, 1)->startOfDay();
        $deptMap = $this->ensureDepartments();
        $usedNik = [];
        $newLogs = 0;
        $refreshed = 0;

        foreach ($rows as $idx => $r) {
            $name = trim($r['Nama Lengkap'] ?? '');
            if ($name === '') {
                continue;
            }
            $no = trim($r['No'] ?? '') !== '' ? trim($r['No']) : (string) $idx;
            $jabatan = trim($r['Jabatan'] ?? '') ?: 'Staff';

            $user = User::updateOrCreate(
                ['email' => self::EMAIL_PREFIX.$no.'@'.self::EMAIL_DOMAIN],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::random(40)),
                ]
            );

            // nik is UNIQUE; null it on any clash (within-run, or a pre-existing real
            // employee already holding this KTP) so a collision degrades to null
            // instead of aborting the seed mid-loop.
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
                    'date_of_birth' => $this->parseIndoDate($r['Tanggal Lahir'] ?? ''),
                    'place_of_birth' => trim($r['Tempat Lahir'] ?? '') ?: null,
                    'gender' => $this->mapGender($r['Jenis Kelamin'] ?? ''),
                    'marital_status' => $this->mapMarital($r['Status Pernikahan'] ?? ''),
                    'address' => trim($r['Alamat Domisili saat ini'] ?? '') ?: null,
                    'phone' => trim($r['No HP'] ?? '') ?: (trim($r['Nomor Hp yang dpt dihubungi'] ?? '') ?: null),
                    'emergency_contact_name' => trim($r['Emergency Contact'] ?? '') ?: null,
                    'emergency_contact_phone' => trim($r['Nomor Hp yang dpt dihubungi'] ?? '') ?: null,
                    'bank_name' => trim($r['Nama Bank'] ?? '') ?: null,
                    'bank_account_number' => trim($r['Nomor Rekening'] ?? '') ?: null,
                    'bank_account_holder' => $name,
                    'bpjs_kesehatan_number' => trim($r['Nomor BPJS Kesehatan'] ?? '') ?: null,
                    'bpjs_ketenagakerjaan_number' => trim($r['Nomor Jamsostek'] ?? '') ?: null,
                    'hire_date' => $hireDate,
                    'employment_status' => 'permanent',
                    'is_active' => true,
                    'metadata' => ['seed_batch' => self::BATCH, 'source_no' => $no],
                ]
            );

            $position = $this->ensurePosition($jabatan);

            EmployeePosition::updateOrCreate(
                ['employee_id' => $employee->id, 'is_primary' => true],
                [
                    'position_id' => $position->id,
                    'department_id' => $this->resolveDepartment($jabatan, $deptMap),
                    'start_date' => $hireDate,
                    'end_date' => null,
                    'metadata' => ['seed_batch' => self::BATCH],
                ]
            );

            // Stagger 07:00–08:50; anything past 08:15 reads as "late".
            $checkIn = $today->copy()->setTime(7, 0)->addMinutes(($idx * 7) % 110);
            $status = $checkIn->lte($today->copy()->setTime(8, 15))
                ? AttendanceLog::STATUS_PRESENT
                : AttendanceLog::STATUS_LATE;

            $log = AttendanceLog::updateOrCreate(
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
                    'metadata' => ['seed_batch' => self::BATCH],
                ]
            );

            $log->wasRecentlyCreated ? $newLogs++ : $refreshed++;
        }

        $this->command?->info(sprintf(
            'Dummy team seeded — employees: %d | attendance today: %d new, %d refreshed | batch: %s',
            $newLogs + $refreshed,
            $newLogs,
            $refreshed,
            self::BATCH
        ));
        $this->command?->warn('Reminder: do NOT run payroll while these exist. Roll back with: php artisan dummy:teardown');
    }

    protected function parseMasterData(): array
    {
        $path = $this->resolveDocPath();
        if (! $path) {
            $this->command?->error('Master data doc not found near base path.');

            return [];
        }

        $content = file_get_contents($path);
        if (! preg_match('/```tsv\s*\n(.*?)\n```/s', $content, $m)) {
            return [];
        }

        $lines = preg_split('/\r?\n/', trim($m[1]));
        $headers = array_map('trim', explode("\t", array_shift($lines)));
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
        foreach ([
            base_path('../docs/employee_master_data_ai_agent.md'),
            base_path('docs/employee_master_data_ai_agent.md'),
        ] as $candidate) {
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

        foreach (['it ', 'it admin', 'it senior', 'engineer', 'software', 'sofware', 'system', 'field', 'development', 'dev '] as $k) {
            if (str_contains($j, $k)) {
                return $map['ENG'] ?? null;
            }
        }
        foreach (['marketing', 'sales', 'social media', 'content', 'design', 'digital', 'photo', 'videograph', 'business development', 'customer service'] as $k) {
            if (str_contains($j, $k)) {
                return $map['MKT'] ?? null;
            }
        }
        foreach (['hr ', 'hr admin', 'recruit', 'training', 'legal'] as $k) {
            if (str_contains($j, $k)) {
                return $map['HR'] ?? null;
            }
        }

        return $map['FIN'] ?? null;
    }

    protected function ensurePosition(string $title): Position
    {
        $title = trim($title);
        $code = self::POSITION_CODE_PREFIX.strtoupper(Str::slug($title, '_'));
        $code = substr($code, 0, 60);

        return Position::firstOrCreate(
            ['code' => $code],
            ['title' => $title, 'level' => $this->levelFor($title)]
        );
    }

    protected function levelFor(string $title): string
    {
        $t = strtolower($title);
        if (str_contains($t, 'manager')) {
            return 'manager';
        }
        if (str_contains($t, 'supervisor') || str_contains($t, 'controller') || str_contains($t, 'senior') || str_contains($t, 'lead')) {
            return 'lead';
        }

        return 'staff';
    }

    protected function parseIndoDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $months = [
            'januari' => 1, 'februari' => 2, 'febuari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5,
            'juni' => 6, 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'agu' => 8, 'aug' => 8,
            'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'des' => 12, 'dec' => 12,
        ];

        if (preg_match('/^(\d{1,2})[\s-]+([A-Za-z]+)[\s-]+(\d{2,4})$/', $raw, $m)) {
            $month = $months[strtolower($m[2])] ?? null;
            if ($month) {
                $year = (int) $m[3];
                if ($year < 100) {
                    $year += $year > 30 ? 1900 : 2000;
                }
                try {
                    return Carbon::create($year, $month, (int) $m[1])->startOfDay();
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        return null;
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

    protected function mapMarital(string $raw): ?string
    {
        $s = strtolower($raw);
        if (str_contains($s, 'belum')) {
            return 'single';
        }
        if (str_contains($s, 'cerai')) {
            return 'divorced';
        }
        if (str_contains($s, 'kawin') || str_contains($s, 'nikah')) {
            return 'married';
        }

        return null;
    }
}
