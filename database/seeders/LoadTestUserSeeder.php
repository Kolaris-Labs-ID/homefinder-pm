<?php

namespace Database\Seeders;

use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seeds the accounts the k6 load test logs in as: loadtest001@example.com and
 * on, each a `member` with an active Employee record (the HRIS API 404s without
 * one). Not called by DatabaseSeeder. Every employee is tagged for rollback via
 * `php artisan loadtest:teardown`.
 *
 *   LOADTEST_PASSWORD=... php artisan db:seed --class=LoadTestUserSeeder --force
 *
 * The password is read from the process environment, never defaulted: the
 * other seeders' 'password' is exactly what must not reach production.
 */
class LoadTestUserSeeder extends Seeder
{
    public const BATCH = 'loadtest_v1';

    public const EMAIL_PREFIX = 'loadtest';

    // Reserved by RFC 2606, so nothing sent to these addresses goes anywhere.
    public const EMAIL_DOMAIN = 'example.com';

    // Real KTP numbers never start with 9999, so these cannot clash with one.
    public const NIK_PREFIX = '9999';

    public const DEFAULT_COUNT = 100;

    public static function email(int $n): string
    {
        return sprintf('%s%03d@%s', self::EMAIL_PREFIX, $n, self::EMAIL_DOMAIN);
    }

    public function run(): void
    {
        // getenv, not env(): env() returns null once the config is cached.
        $password = (string) getenv('LOADTEST_PASSWORD');
        if (strlen($password) < 12) {
            throw new \RuntimeException('Set LOADTEST_PASSWORD (12+ characters) in the environment to seed load-test users.');
        }

        $count = (int) (getenv('LOADTEST_USERS') ?: self::DEFAULT_COUNT);
        if ($count < 1 || $count > 999) {
            throw new \RuntimeException('LOADTEST_USERS must be between 1 and 999.');
        }

        $member = Role::where('name', 'member')->where('guard_name', 'web')->first();
        if (! $member) {
            throw new \RuntimeException('Role "member" is missing — run RoleSeeder first.');
        }

        // One bcrypt for all rows: at cost 12 a hash per user is ~25s for 100.
        $hash = Hash::make($password);
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($count, $hash, $member, &$created, &$updated) {
            for ($n = 1; $n <= $count; $n++) {
                $user = User::updateOrCreate(
                    ['email' => self::email($n)],
                    [
                        'name' => sprintf('Loadtest %03d', $n),
                        'password' => $hash,
                        'email_verified_at' => now(),
                    ]
                );
                $user->wasRecentlyCreated ? $created++ : $updated++;

                $user->syncRoles([$member]);

                Employee::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nik' => self::NIK_PREFIX.str_pad((string) $n, 12, '0', STR_PAD_LEFT),
                        'hire_date' => now()->subYear()->startOfDay(),
                        'employment_status' => 'permanent',
                        'is_active' => true,
                        'metadata' => ['seed_batch' => self::BATCH],
                    ]
                );
            }
        });

        $this->command?->info(sprintf(
            'Load-test users seeded — %d created, %d updated (%s … %s) | batch: %s',
            $created,
            $updated,
            self::email(1),
            self::email($count),
            self::BATCH
        ));
        $this->command?->warn('Roll back with: php artisan loadtest:teardown');
    }
}
