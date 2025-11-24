<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create 
                            {--email=admin@example.com : Email address for the admin user}
                            {--password=password : Password for the admin user}
                            {--name=Administrator : Name for the admin user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin user with super_admin role and all permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $name = $this->option('name');

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            if (!$this->confirm("User with email {$email} already exists. Do you want to update it?", true)) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        // Ensure super_admin role exists and has all permissions
        $this->info('Setting up super_admin role...');
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web']
        );

        // Assign ALL permissions to super_admin role
        $allPermissions = Permission::where('guard_name', 'web')->get();
        $superAdminRole->syncPermissions($allPermissions);
        $this->info("✓ Assigned {$allPermissions->count()} permissions to super_admin role.");

        // Ensure panel_user role exists
        $panelUserRole = Role::firstOrCreate(
            ['name' => 'panel_user', 'guard_name' => 'web']
        );

        // Create or update admin user
        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        // Assign roles
        if (!$admin->hasRole('super_admin')) {
            $admin->assignRole($superAdminRole);
            $this->info('✓ Assigned super_admin role.');
        } else {
            $this->info('✓ User already has super_admin role.');
        }

        if (!$admin->hasRole('panel_user')) {
            $admin->assignRole($panelUserRole);
            $this->info('✓ Assigned panel_user role.');
        }

        $this->newLine();
        $this->info('✓ Admin user created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Email', $email],
                ['Password', $password],
                ['Name', $name],
                ['Roles', $admin->roles->pluck('name')->join(', ')],
                ['Permissions Count', $admin->getAllPermissions()->count()],
            ]
        );

        $this->warn('⚠️  Please change the password after first login!');

        return Command::SUCCESS;
    }
}





