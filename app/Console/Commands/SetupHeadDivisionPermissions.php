<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;

class SetupHeadDivisionPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:head-division-permissions {--list : List current Head Division users} {--user= : Assign Head Division role to specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Complete setup for Head Division role permissions and user assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Head Division Permissions Setup');
        $this->newLine();

        try {
            // Step 1: Ensure Head Division role exists and has permissions
            $this->info('Step 1: Setting up Head Division role and permissions...');
            $this->setupHeadDivisionRole();
            
            // Step 2: List current Head Division users if requested
            if ($this->option('list')) {
                $this->newLine();
                $this->info('Step 2: Current Head Division users...');
                $this->listHeadDivisionUsers();
            }
            
            // Step 3: Assign role to specific user if provided
            if ($userId = $this->option('user')) {
                $this->newLine();
                $this->info("Step 3: Assigning Head Division role to user ID: {$userId}...");
                $this->assignUserToHeadDivision($userId);
            }
            
            $this->newLine();
            $this->info('✅ Setup completed successfully!');
            
            // Show next steps
            $this->showNextSteps();

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Setup failed: ' . $e->getMessage());
            Log::error('Head Division setup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function setupHeadDivisionRole()
    {
        // Find or create the Head Division role
        $headDivisionRole = Role::firstOrCreate(['name' => 'Head Division']);
        
        if ($headDivisionRole->wasRecentlyCreated) {
            $this->info('  ✓ Created Head Division role');
        } else {
            $this->info('  ✓ Found existing Head Division role');
        }

        // Get all project-related permissions
        $projectPermissions = Permission::where('name', 'like', '%project%')->get();
        
        if ($projectPermissions->isEmpty()) {
            $this->warn('  ⚠ No project permissions found. Run: php artisan shield:generate --all');
            return;
        }

        $this->info("  Found {$projectPermissions->count()} project permissions");

        // Grant permissions to Head Division role
        $grantedCount = 0;
        foreach ($projectPermissions as $permission) {
            if (!$headDivisionRole->hasPermissionTo($permission)) {
                $headDivisionRole->givePermissionTo($permission);
                $grantedCount++;
            }
        }

        // Also ensure view_any_project permission
        $viewAnyPermission = Permission::where('name', 'view_any_project')->first();
        if ($viewAnyPermission && !$headDivisionRole->hasPermissionTo($viewAnyPermission)) {
            $headDivisionRole->givePermissionTo($viewAnyPermission);
            $grantedCount++;
        }

        $this->info("  ✓ Granted {$grantedCount} permissions to Head Division role");
        
        // Show final permissions count
        $finalCount = $headDivisionRole->permissions()->where('name', 'like', '%project%')->count();
        $this->info("  ✓ Total project permissions: {$finalCount}");
    }

    private function listHeadDivisionUsers()
    {
        $headDivisionUsers = User::role('Head Division')->with('roles')->get();
        
        if ($headDivisionUsers->isEmpty()) {
            $this->warn('  ⚠ No users found with Head Division role');
            return;
        }

        $this->info("  Found {$headDivisionUsers->count()} Head Division users:");
        
        $headers = ['ID', 'Name', 'Email', 'Division', 'Division Head Flag'];
        $rows = [];

        foreach ($headDivisionUsers as $user) {
            $isDivisionHead = isset($user->is_division_head) && $user->is_division_head ? 'Yes' : 'No';
            $rows[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->division ?? 'Not set',
                $isDivisionHead
            ];
        }

        $this->table($headers, $rows);
    }

    private function assignUserToHeadDivision($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("  ❌ User with ID {$userId} not found");
            return;
        }

        $this->info("  ✓ Found user: {$user->name} ({$user->email})");

        // Assign Head Division role
        if ($user->hasRole('Head Division')) {
            $this->warn("  ⚠ User already has Head Division role");
        } else {
            $user->assignRole('Head Division');
            $this->info("  ✓ Assigned Head Division role to {$user->name}");
        }

        // Update is_division_head flag if exists
        if (isset($user->is_division_head)) {
            $user->update(['is_division_head' => true]);
            $this->info("  ✓ Updated is_division_head flag");
        }

        // Show division info
        if ($user->division) {
            $this->info("  ✓ Division: {$user->division}");
        } else {
            $this->warn("  ⚠ User's division is not set");
        }

        Log::info('User assigned to Head Division role', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email
        ]);
    }

    private function showNextSteps()
    {
        $this->newLine();
        $this->info('📋 Next Steps:');
        $this->line('1. List all users to find user IDs:');
        $this->line('   php artisan users:list');
        $this->newLine();
        $this->line('2. Assign Head Division role to users:');
        $this->line('   php artisan setup:head-division-permissions --user=USER_ID');
        $this->newLine();
        $this->line('3. List current Head Division users:');
        $this->line('   php artisan setup:head-division-permissions --list');
        $this->newLine();
        $this->line('4. Verify permissions:');
        $this->line('   php artisan permission:show | grep project');
        $this->newLine();
        $this->info('✨ Head Division users can now:');
        $this->line('   • Edit project members');
        $this->line('   • Add new project members');
        $this->line('   • View and manage all projects');
        $this->line('   • Access project management features');
    }
}
