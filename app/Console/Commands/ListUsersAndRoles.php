<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class ListUsersAndRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:list {--role= : Filter by specific role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all users with their roles and division information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $roleFilter = $this->option('role');
        
        $this->info('Listing users and their roles...');
        
        try {
            $query = User::with('roles');
            
            // Apply role filter if specified
            if ($roleFilter) {
                $query->whereHas('roles', function ($q) use ($roleFilter) {
                    $q->where('name', $roleFilter);
                });
                $this->info("Filtering by role: {$roleFilter}");
            }

            $users = $query->orderBy('name')->get();

            if ($users->isEmpty()) {
                $this->warn('No users found' . ($roleFilter ? ' with role: ' . $roleFilter : ''));
                return 0;
            }

            $this->info('Found ' . $users->count() . ' users');
            $this->newLine();

            // Create table headers
            $headers = ['ID', 'Name', 'Email', 'Division', 'Division Head', 'Roles'];
            $rows = [];

            foreach ($users as $user) {
                $roles = $user->roles->pluck('name')->implode(', ') ?: 'None';
                $isDivisionHead = isset($user->is_division_head) && $user->is_division_head ? 'Yes' : 'No';
                
                $rows[] = [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->division ?? 'Not set',
                    $isDivisionHead,
                    $roles
                ];
            }

            $this->table($headers, $rows);

            // Show role statistics
            $this->newLine();
            $this->info('Role Statistics:');
            
            $roleStats = Role::withCount('users')->orderBy('name')->get();
            foreach ($roleStats as $role) {
                $this->line("  {$role->name}: {$role->users_count} user(s)");
            }

            // Show Head Division users specifically
            $headDivisionUsers = User::role('Head Division')->get();
            if ($headDivisionUsers->isNotEmpty()) {
                $this->newLine();
                $this->info('Head Division Users:');
                foreach ($headDivisionUsers as $user) {
                    $this->line("  ID: {$user->id} - {$user->name} ({$user->email}) - Division: " . ($user->division ?? 'Not set'));
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }
}
