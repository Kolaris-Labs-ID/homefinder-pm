<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;

class GrantProjectPermissionsToHeadDivision extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:grant-project-to-head-division';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant all project-related permissions to Head Division role';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Granting project permissions to Head Division role...');
        
        try {
            // Find or create the Head Division role
            $headDivisionRole = Role::firstOrCreate(['name' => 'Head Division']);
            
            if ($headDivisionRole->wasRecentlyCreated) {
                $this->info('✓ Created Head Division role');
            } else {
                $this->info('✓ Found existing Head Division role');
            }

            // Get all project-related permissions
            $projectPermissions = Permission::where('name', 'like', '%project%')->get();
            
            if ($projectPermissions->isEmpty()) {
                $this->warn('⚠ No project permissions found. You may need to run: php artisan shield:generate --all');
                return 1;
            }

            $this->info('Found ' . $projectPermissions->count() . ' project permissions');

            // Grant permissions to Head Division role
            $grantedCount = 0;
            foreach ($projectPermissions as $permission) {
                if (!$headDivisionRole->hasPermissionTo($permission)) {
                    $headDivisionRole->givePermissionTo($permission);
                    $grantedCount++;
                    $this->line("  ✓ Granted: {$permission->name}");
                } else {
                    $this->line("  - Already has: {$permission->name}");
                }
            }

            // Also grant view_any_project if not already included
            $viewAnyPermission = Permission::where('name', 'view_any_project')->first();
            if ($viewAnyPermission && !$headDivisionRole->hasPermissionTo($viewAnyPermission)) {
                $headDivisionRole->givePermissionTo($viewAnyPermission);
                $grantedCount++;
                $this->line("  ✓ Granted: view_any_project");
            }

            $this->info("✅ Successfully granted {$grantedCount} new permissions to Head Division role");
            
            // Show final permissions
            $finalPermissions = $headDivisionRole->permissions()->where('name', 'like', '%project%')->pluck('name');
            $this->info('Total project permissions: ' . $finalPermissions->count());
            
            // Show users with Head Division role
            $headDivisionUsers = \App\Models\User::role('Head Division')->get();
            if ($headDivisionUsers->isNotEmpty()) {
                $this->info('Users with Head Division role:');
                foreach ($headDivisionUsers as $user) {
                    $this->line("  - {$user->name} ({$user->email})");
                }
            } else {
                $this->warn('⚠ No users found with Head Division role');
                $this->info('To assign a user to Head Division role:');
                $this->info('  php artisan tinker');
                $this->info('  > $user = App\Models\User::find(1); // Replace with user ID');
                $this->info('  > $user->assignRole("Head Division");');
            }

            Log::info('Head Division role granted project permissions', [
                'permissions_granted' => $grantedCount,
                'total_permissions' => $finalPermissions->count()
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Failed to grant project permissions to Head Division', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
