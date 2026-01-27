<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

class FixMissingMembersTab extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:fix-members-tab {--user= : Test specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comprehensive fix for missing Members tab in projects';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Comprehensive Members Tab Fix');
        $this->newLine();

        try {
            // Step 1: Create all possible permissions
            $this->info('Step 1: Creating all required permissions...');
            
            $permissions = [
                // Basic project permissions
                'view_any_project',
                'view_project', 
                'create_project',
                'update_project',
                'delete_project',
                
                // Relation manager permissions (Filament specific)
                'view_project::relationManagers',
                'manage_project::relationManagers',
                'view_relation_managers',
                'manage_relation_managers',
                
                // User permissions (for member selection)
                'view_any_user',
                'view_user',
                
                // Additional permissions that might be needed
                'view_any_role',
                'view_role',
            ];

            $createdCount = 0;
            foreach ($permissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if (!$permission) {
                    Permission::create(['name' => $permissionName, 'guard_name' => 'web']);
                    $this->line("  ✓ Created: {$permissionName}");
                    $createdCount++;
                } else {
                    $this->line("  - Exists: {$permissionName}");
                }
            }
            
            $this->info("  ✅ Created {$createdCount} new permissions");

            // Step 2: Grant permissions to all roles
            $this->newLine();
            $this->info('Step 2: Granting permissions to all roles...');
            
            $roles = Role::all();
            foreach ($roles as $role) {
                $this->info("  Processing role: {$role->name}");
                $grantedCount = 0;
                
                foreach ($permissions as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission && !$role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                        $grantedCount++;
                    }
                }
                
                if ($grantedCount > 0) {
                    $this->line("    ✓ Granted {$grantedCount} permissions to {$role->name}");
                }
            }

            // Step 3: Test specific user if provided
            if ($userId = $this->option('user')) {
                $this->newLine();
                $this->info("Step 3: Testing User ID: {$userId}");
                
                $user = User::find($userId);
                if (!$user) {
                    $this->error("  ❌ User not found");
                    return 1;
                }

                $this->info("  ✓ User: {$user->name} ({$user->email})");
                
                // Check user's projects
                $userProjects = Project::whereHas('members', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->get();
                
                $this->info("  ✓ User is member of {$userProjects->count()} projects:");
                foreach ($userProjects as $project) {
                    $this->line("    - {$project->name} ({$project->members_count} members)");
                }
                
                // Check user permissions
                $this->newLine();
                $this->info("  User Permissions:");
                $checkPermissions = ['view_any_project', 'view_project', 'update_project', 'view_project::relationManagers'];
                foreach ($checkPermissions as $perm) {
                    $has = $user->can($perm) ? 'YES' : 'NO';
                    $this->line("    - {$perm}: {$has}");
                }
            }

            // Step 4: Check if MembersRelationManager is properly registered
            $this->newLine();
            $this->info('Step 4: Checking MembersRelationManager registration...');
            
            $projectResource = new \App\Filament\Resources\ProjectResource();
            $relations = $projectResource->getRelations();
            
            $this->info("  Registered relation managers:");
            foreach ($relations as $relation) {
                $this->line("    - {$relation}");
            }
            
            $hasMembersRelation = in_array(\App\Filament\Resources\ProjectResource\RelationManagers\MembersRelationManager::class, $relations);
            if ($hasMembersRelation) {
                $this->info("  ✓ MembersRelationManager is registered");
            } else {
                $this->error("  ❌ MembersRelationManager is NOT registered!");
                $this->line("  Check ProjectResource::getRelations() method");
            }

            // Step 5: Manual troubleshooting steps
            $this->newLine();
            $this->info('🔍 Manual Troubleshooting Steps:');
            $this->newLine();
            
            $this->line('1. Clear ALL caches:');
            $this->line('   php artisan cache:clear');
            $this->line('   php artisan permission:cache-reset');
            $this->line('   php artisan config:clear');
            $this->line('   php artisan view:clear');
            $this->line('   php artisan route:clear');
            $this->newLine();
            
            $this->line('2. Check browser:');
            $this->line('   - Open browser developer tools (F12)');
            $this->line('   - Look for JavaScript errors in Console tab');
            $this->line('   - Check Network tab for failed requests');
            $this->line('   - Clear browser cache (Ctrl+Shift+Del)');
            $this->newLine();
            
            $this->line('3. Verify project membership:');
            $this->line('   - User must be a member of the project');
            $this->line('   - Check project_members table');
            $this->line('   - For C-Level/Super Admin: should see all projects');
            $this->newLine();
            
            $this->line('4. Test with different user:');
            $this->line('   - Try with super_admin user');
            $this->line('   - Try adding user to a project manually');
            $this->newLine();

            // Step 6: Database check
            $this->newLine();
            $this->info('Step 6: Database Check...');
            
            $projectCount = Project::count();
            $memberCount = \DB::table('project_members')->count();
            $this->info("  ✓ Projects: {$projectCount}");
            $this->info("  ✓ Project Members: {$memberCount}");
            
            if ($memberCount === 0) {
                $this->warn("  ⚠ No project members found in database!");
                $this->line("  Add some members to projects first");
            }

            Log::info('Comprehensive members tab fix completed', [
                'permissions_created' => $createdCount,
                'roles_updated' => $roles->count()
            ]);

            $this->newLine();
            $this->info('✅ Comprehensive fix completed!');
            $this->newLine();
            $this->info('📋 Next Steps:');
            $this->line('1. Deploy this command to server');
            $this->line('2. Run: php artisan project:fix-members-tab');
            $this->line('3. Clear all caches');
            $this->line('4. Test in browser with fresh session');
            
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Fix failed: ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());
            Log::error('Comprehensive members tab fix failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
