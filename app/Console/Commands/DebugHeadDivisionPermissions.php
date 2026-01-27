<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Project;
use App\Helpers\RoleHelper;

class DebugHeadDivisionPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:head-division-permissions {--user= : User ID to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug Head Division permissions and identify issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Debugging Head Division Permissions');
        $this->newLine();

        try {
            // Test 1: Check if Head Division role exists
            $this->info('1. Checking Head Division Role:');
            $headDivisionRole = \Spatie\Permission\Models\Role::where('name', 'Head Division')->first();
            
            if ($headDivisionRole) {
                $this->info("   ✓ Head Division role exists (ID: {$headDivisionRole->id})");
                
                // Show permissions
                $permissions = $headDivisionRole->permissions()->where('name', 'like', '%project%')->pluck('name');
                $this->info("   ✓ Project permissions: " . $permissions->count());
                foreach ($permissions as $perm) {
                    $this->line("     - {$perm}");
                }
            } else {
                $this->error("   ❌ Head Division role not found!");
                $this->line("   Run: php artisan setup:head-division-permissions");
                return 1;
            }

            // Test 2: Check Head Division users
            $this->newLine();
            $this->info('2. Checking Head Division Users:');
            $headDivisionUsers = User::role('Head Division')->get();
            
            if ($headDivisionUsers->isEmpty()) {
                $this->warn("   ⚠ No users found with Head Division role");
                $this->line("   Run: php artisan setup:head-division-permissions --user=USER_ID");
            } else {
                $this->info("   ✓ Found {$headDivisionUsers->count()} Head Division users:");
                foreach ($headDivisionUsers as $user) {
                    $this->line("     - ID: {$user->id} - {$user->name} ({$user->email})");
                }
            }

            // Test 3: Test specific user if provided
            if ($userId = $this->option('user')) {
                $this->newLine();
                $this->info("3. Testing User ID: {$userId}");
                
                $user = User::find($userId);
                if (!$user) {
                    $this->error("   ❌ User not found");
                    return 1;
                }

                $this->info("   ✓ User: {$user->name} ({$user->email})");
                
                // Check role
                $isHeadDivision = RoleHelper::isHeadDivision($user);
                $this->line("   ✓ Is Head Division: " . ($isHeadDivision ? 'YES' : 'NO'));
                
                // Check permissions
                $canViewAny = $user->can('view_any_project');
                $canUpdate = $user->can('update_project');
                $this->line("   ✓ Can view_any_project: " . ($canViewAny ? 'YES' : 'NO'));
                $this->line("   ✓ Can update_project: " . ($canUpdate ? 'YES' : 'NO'));

                // Test RoleHelper functions
                $this->newLine();
                $this->info("4. Testing RoleHelper Functions:");
                
                // Test isHeadDivision
                $this->line("   ✓ RoleHelper::isHeadDivision(): " . (RoleHelper::isHeadDivision($user) ? 'YES' : 'NO'));
                
                // Test scopeAllowedAssignees
                $allowedQuery = RoleHelper::scopeAllowedAssignees(\App\Models\User::query(), $user);
                $allowedCount = $allowedQuery->count();
                $this->line("   ✓ Can assign to {$allowedCount} users");
                
                // Test canAssignTo with a sample user
                $sampleUser = User::where('id', '!=', $user->id)->first();
                if ($sampleUser) {
                    $canAssign = RoleHelper::canAssignTo($sampleUser, $user);
                    $this->line("   ✓ Can assign to {$sampleUser->name}: " . ($canAssign ? 'YES' : 'NO'));
                }

                // Test project access
                $this->newLine();
                $this->info("5. Testing Project Access:");
                
                $totalProjects = Project::count();
                $userProjects = Project::whereHas('members', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->count();
                
                $this->line("   ✓ Total projects: {$totalProjects}");
                $this->line("   ✓ Projects user is member of: {$userProjects}");
                
                // Test the ProjectResource query logic
                $projectResourceQuery = \App\Filament\Resources\ProjectResource::getEloquentQuery();
                $accessibleProjects = $projectResourceQuery->count();
                $this->line("   ✓ Projects accessible via ProjectResource: {$accessibleProjects}");
                
                if ($accessibleProjects < $totalProjects && $isHeadDivision) {
                    $this->warn("   ⚠ ISSUE: Head Division user can't access all projects!");
                    $this->line("   This is likely due to getEloquentQuery() restriction in ProjectResource.php");
                }
            }

            $this->newLine();
            $this->info('🔧 Potential Issues & Solutions:');
            $this->line('1. If Head Division users can see projects but not add members:');
            $this->line('   - Check if they have "update_project" permission');
            $this->line('   - Verify RoleHelper::isHeadDivision() returns true');
            $this->newLine();
            $this->line('2. If Head Division users can\'t see all projects:');
            $this->line('   - The getEloquentQuery() in ProjectResource.php needs modification');
            $this->line('   - Head Division should be treated like super_admin');
            $this->newLine();
            $this->line('3. If users dropdown is empty when adding members:');
            $this->line('   - Check RoleHelper::scopeAllowedAssignees()');
            $this->line('   - Verify user has proper role assignment');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Debug failed: ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}
