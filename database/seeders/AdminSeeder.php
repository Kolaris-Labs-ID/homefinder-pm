<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, ensure all Filament Shield permissions exist
        // This will sync permissions from Filament resources, pages, and widgets
        $this->command->info('Syncing Filament Shield permissions...');
        $this->syncFilamentPermissions();

        // Create or get super_admin role
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web']
        );

        // Assign ALL permissions to super_admin role
        $allPermissions = Permission::where('guard_name', 'web')->get();
        $superAdminRole->syncPermissions($allPermissions);
        $this->command->info("Assigned {$allPermissions->count()} permissions to super_admin role.");

        // Ensure panel_user role exists (required by Filament Shield)
        $panelUserRole = Role::firstOrCreate(
            ['name' => 'panel_user', 'guard_name' => 'web']
        );

        // Create admin user
        $adminEmail = 'admin@example.com';
        $adminPassword = 'password'; // Change this in production!

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrator',
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]
        );

        // Assign roles to admin user
        if (!$admin->hasRole('super_admin')) {
            $admin->assignRole($superAdminRole);
        }
        if (!$admin->hasRole('panel_user')) {
            $admin->assignRole($panelUserRole);
        }

        $this->command->info('Admin user created successfully!');
        $this->command->info("Email: {$adminEmail}");
        $this->command->info("Password: {$adminPassword}");
        $this->command->warn('⚠️  Please change the password after first login!');
    }

    /**
     * Sync Filament Shield permissions
     * This ensures all permissions from Filament resources, pages, and widgets are created
     */
    private function syncFilamentPermissions(): void
    {
        // Get all Filament resources
        $resources = [
            'project',
            'ticket',
            'ticket_priority',
            'ticket_comment',
            'notification',
            'user',
            'weekly_todo',
        ];

        // Standard resource permissions from Filament Shield config
        $resourceActions = [
            'view',
            'view_any',
            'create',
            'update',
            'restore',
            'restore_any',
            'replicate',
            'reorder',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
        ];

        // Create resource permissions
        foreach ($resources as $resource) {
            foreach ($resourceActions as $action) {
                Permission::firstOrCreate(
                    ['name' => "{$action}_{$resource}", 'guard_name' => 'web']
                );
            }
        }

        // Get Filament pages (excluding Dashboard which is excluded in config)
        $pages = [
            'EpicsOverview',
            'Leaderboard',
            'ProjectBoard',
            'ProjectTimeline',
            'TicketTimeline',
            'UserContributions',
        ];

        foreach ($pages as $page) {
            Permission::firstOrCreate(
                ['name' => "page_{$page}", 'guard_name' => 'web']
            );
        }

        // Get Filament widgets (excluding AccountWidget and FilamentInfoWidget)
        $widgets = [
            'CalendarWidget',
            'ProjectStatusChart',
            'ProjectTimeline',
            'RecentActivityTable',
            'StatsOverview',
            'UserWeeklyTodoWidget',
            'WeeklyTodoOverviewWidget',
        ];

        foreach ($widgets as $widget) {
            Permission::firstOrCreate(
                ['name' => "widget_{$widget}", 'guard_name' => 'web']
            );
        }

        // Shield role management permissions
        $shieldPermissions = [
            'view_role',
            'view_any_role',
            'create_role',
            'update_role',
            'delete_role',
            'delete_any_role',
        ];

        foreach ($shieldPermissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        $this->command->info('Filament Shield permissions synced successfully.');
    }
}

