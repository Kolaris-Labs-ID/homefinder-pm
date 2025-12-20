<?php

/**
 * Script to set a password for a user.
 *
 * Usage: php set-user-password.php email@example.com 'new-password'
 *
 * This is useful for:
 * - Google OAuth users who need API access
 * - Resetting forgotten passwords
 * - Setting up test accounts
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== Set User Password ===" . PHP_EOL . PHP_EOL;

if ($argc < 3) {
    echo "Usage: php set-user-password.php <email> <new-password>" . PHP_EOL;
    echo PHP_EOL;
    echo "Example:" . PHP_EOL;
    echo "  php set-user-password.php ari42k@gmail.com 'MyNewPassword123'" . PHP_EOL;
    exit(1);
}

$email = $argv[1];
$newPassword = $argv[2];

// Validate password length
if (strlen($newPassword) < 8) {
    echo "❌ ERROR: Password must be at least 8 characters long." . PHP_EOL;
    exit(1);
}

// Find user
$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ ERROR: User not found: {$email}" . PHP_EOL;
    echo PHP_EOL . "Available users:" . PHP_EOL;
    User::take(10)->get()->each(function ($u) {
        echo "  - {$u->email}" . PHP_EOL;
    });
    exit(1);
}

echo "User found:" . PHP_EOL;
echo "  ID: {$user->id}" . PHP_EOL;
echo "  Name: {$user->name}" . PHP_EOL;
echo "  Email: {$user->email}" . PHP_EOL;
echo PHP_EOL;

// Check current password status
$hadPassword = !empty($user->password);
echo "Previous password: " . ($hadPassword ? 'Yes (will be replaced)' : 'None (Google OAuth user)') . PHP_EOL;
echo PHP_EOL;

// Set new password
$user->password = Hash::make($newPassword);
$user->save();

echo "✓ Password updated successfully!" . PHP_EOL;
echo PHP_EOL;

// Verify the new password works
$verified = Hash::check($newPassword, $user->fresh()->password);
echo "Verification: " . ($verified ? '✓ Password hash verified' : '❌ Verification failed') . PHP_EOL;

echo PHP_EOL;
echo "=== NEXT STEPS ===" . PHP_EOL;
echo "Test the login with:" . PHP_EOL;
echo PHP_EOL;
echo "curl -X POST https://pm.partnershub.co/api/auth/login \\" . PHP_EOL;
echo "  -H 'Content-Type: application/json' \\" . PHP_EOL;
echo "  -H 'Accept: application/json' \\" . PHP_EOL;
echo "  -d '{" . PHP_EOL;
echo "    \"email\": \"{$email}\"," . PHP_EOL;
echo "    \"password\": \"{$newPassword}\"," . PHP_EOL;
echo "    \"device_name\": \"mobile-app\"" . PHP_EOL;
echo "  }'" . PHP_EOL;
echo PHP_EOL;
