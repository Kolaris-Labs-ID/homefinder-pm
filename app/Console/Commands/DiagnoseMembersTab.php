<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class DiagnoseMembersTab extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:diagnose-members-tab {user_id : User ID to diagnose}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose why a specific user cannot see Members tab';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $this->info('🔍 Diagnosing Members Tab Issue');
        $this->info("User ID: {$userId}");
        $this->newLine();

        try {
            // Step 1: Get user info
            $user = User::find($userId);
            if (!$user) {
                $this->error("❌ User with ID {$userId} not found");
                return 1;
            }

            $this->info("✓ User Found: {$user->name} ({$user->email})");
            $this->line("Roles: " . $user->roles->pluck('name')->implode(', '));
            $this->newLine();

            // Step 2: Check permissions
            $this->info('📋 Permission Check:');
            $permissions = [
                'view_any_project',
                'view_project',
                'update_project',
                'view_project::relationManagers',
                'manage_project::relationManagers',
            ];

            foreach ($permissions as $perm) {
                $has = $user->can($perm) ? '✓ YES' : '✗ NO';
                $this->line("  {$perm}: {$has}");
            }
            $this->newLine();

            // Step 3: Check project membership
            $this->info('🏢 Project Membership:');
            $userProjects = Project::whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->get();

            if ($userProjects->isEmpty()) {
                $this->warn("  ⚠ User is NOT a member of any projects!");
                $this->line("  This is likely why Members tab is not showing.");
                $this->newLine();
                $this->info('💡 Solution:');
                $this->line('  1. Add user to a project as member');
                $this->line('  2. Or use C-Level/Super Admin role to see all projects');
            } else {
                $this->info("  ✓ User is member of {$userProjects->count()} projects:");
                foreach ($userProjects as $project) {
                    $memberCount = $project->members()->count();
                    $this->line("    - {$project->name} ({$memberCount} members)");
                }
            }
            $this->newLine();

            // Step 4: Check if user should see all projects
            $this->info('👑 Access Level Check:');
            $isSuperAdmin = $user->hasRole('super_admin');
            $isCLevel = $user->hasAnyRole(['CEO', 'COO', 'CTO', 'HR']);
            
            $this->line("  Super Admin: " . ($isSuperAdmin ? '✓ YES' : '✗ NO'));
            $this->line("  C-Level: " . ($isCLevel ? '✓ YES' : '✗ NO'));
            
            if ($isSuperAdmin || $isCLevel) {
                $this->info("  ✓ User should see ALL projects");
                $allProjects = Project::count();
                $this->line("  Total projects in system: {$allProjects}");
            } else {
                $this->info("  User should only see projects they are member of");
            }
            $this->newLine();

            // Step 5: Database check
            $this->info('🗄️ Database Check:');
            $totalProjects = Project::count();
            $totalMembers = DB::table('project_members')->count();
            $userMemberEntries = DB::table('project_members')->where('user_id', $userId)->count();
            
            $this->line("  Total projects: {$totalProjects}");
            $this->line("  Total project members: {$totalMembers}");
            $this->line("  User's project memberships: {$userMemberEntries}");
            $this->newLine();

            // Step 6: Recommendations
            $this->info('💡 Recommendations:');
            
            if ($userProjects->isEmpty()) {
                $this->line('1. Add user to a project:');
                $this->line('   php artisan tinker');
                $this->line("   > \$user = App\Models\User::find({$userId});");
                $this->line('   > $project = App\Models\Project::first();');
                $this->line('   > $project->members()->attach($user->id);');
                $this->newLine();
            }
            
            if (!$user->can('update_project')) {
                $this->line('2. Grant required permissions:');
                $this->line('   php artisan project:fix-members-tab');
                $this->newLine();
            }
            
            $this->line('3. Clear caches:');
            $this->line('   php artisan cache:clear');
            $this->line('   php artisan permission:cache-reset');
            $this->newLine();
            
            $this->line('4. Test in browser:');
            $this->line('   - Login as user');
            $this->line('   - Go to Project Management → Projects');
            $this->line('   - Click on any project user can see');
            $this->line('   - Look for "Members" tab');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Diagnosis failed: ' . $e->getMessage());
            return 1;
        }
    }
}
