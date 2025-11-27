<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class UpdateGoogleUserAvatars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-google-avatars';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report Google users who need avatar URL updates (avatars will be updated automatically on next Google login)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if avatar_url column exists
        if (!Schema::hasColumn('users', 'avatar_url')) {
            $this->error('The avatar_url column does not exist in the users table.');
            $this->info('Please run the migration first: php artisan migrate');
            return Command::FAILURE;
        }

        $usersWithoutAvatars = User::whereNotNull('google_id')
            ->where(function ($query) {
                $query->whereNull('avatar_url')
                    ->orWhere('avatar_url', '');
            })
            ->get();

        if ($usersWithoutAvatars->isEmpty()) {
            $this->info('All Google users have avatar URLs set.');
            return Command::SUCCESS;
        }

        $this->info("Found {$usersWithoutAvatars->count()} Google user(s) without avatar URLs:");
        $this->newLine();

        $headers = ['ID', 'Name', 'Email', 'Google ID'];
        $rows = $usersWithoutAvatars->map(function ($user) {
            return [
                $user->id,
                $user->name,
                $user->email,
                $user->google_id,
            ];
        })->toArray();

        $this->table($headers, $rows);
        $this->newLine();
        $this->info('Note: Avatar URLs will be automatically updated when these users log in through Google authentication.');

        return Command::SUCCESS;
    }
}
