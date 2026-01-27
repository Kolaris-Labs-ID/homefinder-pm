<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;

class FixMemberTabPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:fix-member-tab {--role= : Fix for specific role only (default: all roles)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix missing Members tab by granting required permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Fixing Members Tab Permissions');
        $this->newLine();

        try {
            // Step 1: Ensure all required permissions exist
            $this->info('Step 1: Checking required permissions...');
            
            $requiredPermissions = [
                'view_any_project',
                'view_project',
                'update_project',
                'view_project::relationManagers',
                'manage_project::relationManagers',
            ];

            foreach ($requiredPermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if (!$permission) {
                    Permission::create(['name' => $permissionName]);
                    $this->line("  ✓ Created permission: {$permissionName}");
                } else {
                    $this->line("  - Permission exists: {$permissionName}");
                }
            }

            // Step 2: Grant permissions to roles
            $this->newLine();
            $this->info('Step 2: Granting permissions to roles...');
            
            $targetRole = $this->option('role');
            $roles = $targetRole ? [Role::where('name', $targetRole)->first()] : Role::all();
            
            if ($targetRole && empty($roles[0])) {
                $this->error("❌ Role '{$targetRole}' not found");
                return 1;
            }

            foreach ($roles as $role) {
                if (!$role) continue;
                
                $this->info("  Processing role: {$role->name}");
                $grantedCount = 0;
                
                foreach ($requiredPermissions as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission && !$role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                        $grantedCount++;
                        $this->line("    ✓ Granted: {$permissionName}");
                    } elseif ($permission && $role->hasPermissionTo($permission)) {
                        $this->line("    - Already has: {$permissionName}");
                    }
                }
                
                if ($grantedCount > 0) {
                    $this->info("    ✅ Granted {$grantedCount} new permissions to {$role->name}");
                }
            }

            // Step 3: Check if user is actually a project member
            $this->newLine();
            $this->info('Step 3: Project Membership Check');
            $this->warn('  ⚠ Note: Users can only see Members tab for projects they are members of');
            $this->warn('  ⚠ Exception: C-Level and Super Admin users can see all projects');
            $this->newLine();
            
            $this->info('  To add a user to a project:');
            $this->line('  1. Go to Project Management → Projects');
            $this->line('  2. Edit a project');
            $this->line('  3. Add user to "Project Members" field');
            $this->line('  4. Save the project');
            $this->newLine();

            // Step 4: Clear caches
            $this->info('Step 4: Clearing caches...');
            $this->line('  Run these commands on your server:');
            $this->line('  php artisan cache:clear');
            $this->line('  php artisan permission:cache-reset');
            $this->line('  php artisan config:clear');
            $this->line('  php artisan view:clear');

            // Step 5: Troubleshooting steps
            $this->newLine();
            $this->info('🔍 If Members tab still doesn\'t show:');
            $this->line('1. Verify user is a member of the project');
            $this->line('2. Check browser console for errors');
            $this->line('3. Try refreshing the page (Ctrl+F5)');
            $this->line('4. Check if user has "update_project" permission');
            $this->line('5. Verify project has members assigned');
            $this->newLine();

            $this->info('📋 Quick Test:');
            $this->line('1. Login as user');
            $this->line('2. Go to any project you are member of');
            $this->line('3. Look for "Members" tab');
            $this->line('4. Should see member count badge');

            Log::info('Member tab permissions fixed', [
                'target_role' => $targetRole ?: 'all_roles',
                'permissions_created' => $requiredPermissions
            ]);

            $this->newLine();
            $this->info('✅ Members tab permissions have been fixed!');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Fix failed: ' . $e->getMessage());
            Log::error('Failed to fix member tab permissions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
