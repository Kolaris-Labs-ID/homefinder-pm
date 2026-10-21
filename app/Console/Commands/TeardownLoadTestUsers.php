<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\User;
use Database\Seeders\LoadTestUserSeeder as Seed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeardownLoadTestUsers extends Command
{
    protected $signature = 'loadtest:teardown {--force : Skip confirmation}';

    protected $description = 'Delete every load-test user seeded by LoadTestUserSeeder, with their employees, tokens and everything the load test wrote.';

    public function handle(): int
    {
        $empIds = Employee::where('metadata->seed_batch', Seed::BATCH)->pluck('id');
        // Anchor on the seed_batch; the email pattern catches users whose
        // employee row is already gone. Escape '_' so LIKE stays literal.
        $userIds = Employee::where('metadata->seed_batch', Seed::BATCH)->pluck('user_id')
            ->merge(User::where('email', 'like', Seed::EMAIL_PREFIX.'%@'.addcslashes(Seed::EMAIL_DOMAIN, '%_\\'))->pluck('id'))
            ->unique()
            ->values();

        $userMorph = (new User)->getMorphClass();
        $employeeMorph = (new Employee)->getMorphClass();

        // Polymorphic rows have no foreign key to cascade from, and bulk
        // deletes skip the model events that would otherwise detach them.
        $morphTables = [
            'personal_access_tokens' => ['tokenable_type', 'tokenable_id', [$userMorph => $userIds]],
            config('permission.table_names.model_has_roles') => ['model_type', config('permission.column_names.model_morph_key'), [$userMorph => $userIds]],
            config('permission.table_names.model_has_permissions') => ['model_type', config('permission.column_names.model_morph_key'), [$userMorph => $userIds]],
            'notifications' => ['notifiable_type', 'notifiable_id', [$userMorph => $userIds, $employeeMorph => $empIds]],
            'push_subscriptions' => ['subscribable_type', 'subscribable_id', [$userMorph => $userIds, $employeeMorph => $empIds]],
        ];

        $morphQuery = function (string $table, array $spec) {
            [$typeCol, $idCol, $owners] = $spec;

            return DB::table($table)->where(function ($q) use ($typeCol, $idCol, $owners) {
                foreach ($owners as $type => $ids) {
                    $q->orWhere(fn ($w) => $w->where($typeCol, $type)->whereIn($idCol, $ids));
                }
            });
        };

        $rows = [
            // Everything else keyed on employee_id or user_id cascades in the
            // database (expense requests, visits, leave, overtime logs, ...).
            ['Attendance logs (cascade)', AttendanceLog::whereIn('employee_id', $empIds)->count()],
            ['Employees', $empIds->count()],
            ['Users', $userIds->count()],
        ];
        foreach ($morphTables as $table => $spec) {
            if (Schema::hasTable($table)) {
                $rows[] = [$table, $morphQuery($table, $spec)->count()];
            }
        }
        $this->table(['Resource', 'Count'], $rows);

        if ($empIds->isEmpty() && $userIds->isEmpty()) {
            $this->info('Nothing to tear down.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete all of the above? This cannot be undone.')) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($empIds, $userIds, $morphTables, $morphQuery) {
            foreach ($morphTables as $table => $spec) {
                if (Schema::hasTable($table)) {
                    $morphQuery($table, $spec)->delete();
                }
            }
            Employee::whereIn('id', $empIds)->delete();
            User::whereIn('id', $userIds)->delete();
        });

        $this->info('Teardown complete.');

        return self::SUCCESS;
    }
}
