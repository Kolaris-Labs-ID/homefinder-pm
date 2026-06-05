<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ATS screening permissions (web guard). Screening access is granted explicitly —
 * never inferred from applicant access. hr-staff may view + run; override and clear
 * are stronger authority reserved for hr-admin / super_admin (locked decision Q1).
 */
class RecruitmentScreeningPermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    public const VIEW = 'recruitment.view_screening';

    public const RUN = 'recruitment.run_screening';

    public const OVERRIDE = 'recruitment.override_screening';

    public const CLEAR = 'recruitment.clear_screening_override';

    public function run(): void
    {
        $all = [self::VIEW, self::RUN, self::OVERRIDE, self::CLEAR];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $this->grant('super_admin', $all);
        $this->grant('hr-admin', $all);
        $this->grant('hr-staff', [self::VIEW, self::RUN]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grant(string $role, array $permissions): void
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => self::GUARD])
            ->givePermissionTo($permissions);
    }
}
