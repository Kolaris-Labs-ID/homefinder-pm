<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class AssignHeadDivisionRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:assign-head-division {user_id : The ID of the user to assign as Head Division}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign Head Division role to a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $this->info("Assigning Head Division role to user ID: {$userId}");
        
        try {
            // Find the user
            $user = User::find($userId);
            
            if (!$user) {
                $this->error("❌ User with ID {$userId} not found");
                return 1;
            }

            $this->info("✓ Found user: {$user->name} ({$user->email})");

            // Find or create the Head Division role
            $headDivisionRole = Role::firstOrCreate(['name' => 'Head Division']);
            
            if ($headDivisionRole->wasRecentlyCreated) {
                $this->info('✓ Created Head Division role');
            }

            // Assign the role to the user
            if ($user->hasRole('Head Division')) {
                $this->warn("⚠ User {$user->name} already has Head Division role");
            } else {
                $user->assignRole('Head Division');
                $this->info("✅ Successfully assigned Head Division role to {$user->name}");
            }

            // Show user's current roles
            $roles = $user->roles->pluck('name');
            $this->info("User's current roles: " . $roles->implode(', '));

            // Update is_division_head flag if it exists
            if (isset($user->is_division_head)) {
                $user->update(['is_division_head' => true]);
                $this->info("✓ Updated is_division_head flag to true");
            }

            // Show division if set
            if ($user->division) {
                $this->info("Division: {$user->division}");
            } else {
                $this->warn("⚠ User's division is not set");
            }

            Log::info('User assigned to Head Division role', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'division' => $user->division
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Failed to assign Head Division role', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
