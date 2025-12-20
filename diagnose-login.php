<?php

/**
 * Diagnostic script for API login issues.
 *
 * This script checks:
 * 1. User exists in database
 * 2. User has a password set
 * 3. Password hash verification
 * 4. Google OAuth status
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== Login Diagnostic Tool ===" . PHP_EOL . PHP_EOL;

// Get email from command line or use default
$email = $argv[1] ?? 'ari42k@gmail.com';
$testPassword = $argv[2] ?? null;

echo "Checking user: {$email}" . PHP_EOL . PHP_EOL;

// Find user
$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ ERROR: User not found in database!" . PHP_EOL;
    echo PHP_EOL . "Available users:" . PHP_EOL;
    User::take(10)->get()->each(function ($u) {
        echo "  - {$u->email}" . PHP_EOL;
    });
    exit(1);
}

echo "✓ User found:" . PHP_EOL;
echo "  ID: {$user->id}" . PHP_EOL;
echo "  Name: {$user->name}" . PHP_EOL;
echo "  Email: {$user->email}" . PHP_EOL;
echo "  Created: {$user->created_at}" . PHP_EOL;
echo PHP_EOL;

// Check password
echo "Password Status:" . PHP_EOL;
$hasPassword = !empty($user->password);
echo "  Has password: " . ($hasPassword ? 'YES' : 'NO') . PHP_EOL;

if ($hasPassword) {
    echo "  Password hash: " . substr($user->password, 0, 20) . "..." . PHP_EOL;

    // Check if it's a bcrypt hash
    $isBcrypt = str_starts_with($user->password, '$2y$') || str_starts_with($user->password, '$2a$');
    echo "  Is bcrypt hash: " . ($isBcrypt ? 'YES' : 'NO') . PHP_EOL;

    if ($testPassword) {
        echo PHP_EOL . "Testing password verification..." . PHP_EOL;
        $matches = Hash::check($testPassword, $user->password);
        echo "  Password matches: " . ($matches ? '✓ YES' : '❌ NO') . PHP_EOL;

        if (!$matches) {
            echo PHP_EOL . "  ⚠ The password does not match the stored hash." . PHP_EOL;
            echo "  Possible causes:" . PHP_EOL;
            echo "    1. Wrong password" . PHP_EOL;
            echo "    2. Password was changed" . PHP_EOL;
            echo "    3. User registered via Google OAuth (no password)" . PHP_EOL;
        }
    } else {
        echo PHP_EOL . "  To test password, run: php diagnose-login.php {$email} 'your-password'" . PHP_EOL;
    }
} else {
    echo PHP_EOL . "  ⚠ WARNING: User has NO password set!" . PHP_EOL;
    echo "  This user likely registered via Google OAuth." . PHP_EOL;
    echo "  They cannot login with email/password." . PHP_EOL;
}

echo PHP_EOL;

// Check Google OAuth
echo "Google OAuth Status:" . PHP_EOL;
$hasGoogleId = !empty($user->google_id);
echo "  Has Google ID: " . ($hasGoogleId ? 'YES' : 'NO') . PHP_EOL;
if ($hasGoogleId) {
    echo "  Google ID: {$user->google_id}" . PHP_EOL;
    echo PHP_EOL . "  ℹ This user registered via Google OAuth." . PHP_EOL;
    if (!$hasPassword) {
        echo "  They need to set a password to use API login." . PHP_EOL;
    }
}

echo PHP_EOL;

// Check employee record
echo "Employee Status:" . PHP_EOL;
$employee = $user->employee;
if ($employee) {
    echo "  ✓ Has employee record (ID: {$employee->id})" . PHP_EOL;
    echo "  NIK: " . ($employee->nik ?? 'Not set') . PHP_EOL;
    echo "  Active: " . ($employee->is_active ? 'YES' : 'NO') . PHP_EOL;
} else {
    echo "  ❌ No employee record!" . PHP_EOL;
    echo "  Run: php create-missing-employee-records.php" . PHP_EOL;
}

echo PHP_EOL;

// Summary
echo "=== SUMMARY ===" . PHP_EOL;
if (!$hasPassword) {
    echo "❌ PROBLEM: User has no password (Google OAuth user)" . PHP_EOL;
    echo PHP_EOL . "SOLUTION: Set a password for this user:" . PHP_EOL;
    echo "  php artisan tinker" . PHP_EOL;
    echo "  >>> \$user = User::where('email', '{$email}')->first();" . PHP_EOL;
    echo "  >>> \$user->password = Hash::make('new-password-here');" . PHP_EOL;
    echo "  >>> \$user->save();" . PHP_EOL;
    echo PHP_EOL . "Or run: php set-user-password.php {$email} 'new-password'" . PHP_EOL;
} elseif ($testPassword && !Hash::check($testPassword, $user->password)) {
    echo "❌ PROBLEM: Password does not match" . PHP_EOL;
    echo PHP_EOL . "SOLUTION: Reset the password or use correct password" . PHP_EOL;
} else {
    echo "✓ User configuration looks correct" . PHP_EOL;
    if (!$testPassword) {
        echo "  Run with password to verify: php diagnose-login.php {$email} 'password'" . PHP_EOL;
    }
}

echo PHP_EOL;
