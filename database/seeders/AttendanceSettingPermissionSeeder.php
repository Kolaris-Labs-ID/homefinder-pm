<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AttendanceSettingPermissionSeeder extends Seeder
{
    /**
     * Seed attendance setting permissions and assign them to authorized roles.
     */
    public function run(): void
    {
        $guard = 'web';

        // Create attendance setting permissions
        $permissions = [
            'view_attendance_setting',
            'update_attendance_setting',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);
        }

        // Authorized roles: superadmin, HR, HR Staff, and C-Level (CEO, COO, CTO, HR)
        $authorizedRoles = [
            'super_admin',
            'hr-admin',
            'hr-staff',
            'CEO',
            'COO',
            'CTO',
            'HR',
        ];

        foreach ($authorizedRoles as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ]);

            $role->givePermissionTo($permissions);
        }

        $this->command->info('Attendance setting permissions seeded successfully.');
    }
}
