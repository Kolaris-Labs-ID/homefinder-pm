<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\Position;
use App\Models\User;
use Database\Seeders\DummyTeamAttendanceSeeder as Seed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TeardownDummyTeam extends Command
{
    protected $signature = 'dummy:teardown {--force : Skip confirmation}';

    protected $description = 'Delete every record seeded by DummyTeamAttendanceSeeder (employees, positions, attendance, users).';

    public function handle(): int
    {
        $empIds = Employee::where('metadata->seed_batch', Seed::BATCH)->pluck('id');
        // Anchor the user set to the seed_batch (via employee.user_id); the email
        // pattern is a fallback for users whose employee row was already removed.
        $userIds = Employee::where('metadata->seed_batch', Seed::BATCH)->pluck('user_id')
            ->merge(User::where('email', 'like', Seed::EMAIL_PREFIX.'%@'.Seed::EMAIL_DOMAIN)->pluck('id'))
            ->unique()
            ->values();
        $taggedLogs = AttendanceLog::where('metadata->seed_batch', Seed::BATCH)->count();

        $this->table(['Resource', 'Count'], [
            // Tagged attendance covers BOTH modes: dummy-employee logs (seeder) and
            // real-employee check-ins (dummy:checkin-team) carry the same batch tag.
            ['Attendance logs', $taggedLogs],
            ['Employee positions', EmployeePosition::whereIn('employee_id', $empIds)->count()],
            ['Employees', $empIds->count()],
            ['Users', $userIds->count()],
        ]);

        if ($empIds->isEmpty() && $userIds->isEmpty() && $taggedLogs === 0) {
            $this->info('Nothing to tear down.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete all of the above? This cannot be undone.')) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $removedPositions = 0;

        DB::transaction(function () use ($empIds, $userIds, &$removedPositions) {
            // Delete by the attendance tag directly so real-employee check-ins
            // (dummy:checkin-team) are removed too — their employees are NOT tagged.
            AttendanceLog::where('metadata->seed_batch', Seed::BATCH)->delete();
            EmployeePosition::whereIn('employee_id', $empIds)->delete();
            Employee::whereIn('id', $empIds)->delete();
            User::whereIn('id', $userIds)->delete();

            // Escape the underscore so LIKE matches the literal 'DMY_' prefix, not 'DMY?'.
            $orphanPositionIds = Position::where('code', 'like', addcslashes(Seed::POSITION_CODE_PREFIX, '%_\\').'%')
                ->whereDoesntHave('employeePositions')
                ->pluck('id');
            Position::whereIn('id', $orphanPositionIds)->delete();
            $removedPositions = $orphanPositionIds->count();
        });

        $this->info(sprintf(
            'Teardown complete — removed %d dummy positions. Shared departments left intact.',
            $removedPositions
        ));

        return self::SUCCESS;
    }
}
