<?php

/**
 * Check Admin Accounts in Local Database
 * Run: php check-admin.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

echo "==========================================\n";
echo "Admin Account Check\n";
echo "==========================================\n\n";

// Check total users
$totalUsers = User::count();
echo "Total users in database: {$totalUsers}\n\n";

if ($totalUsers === 0) {
    echo "❌ No users found in database!\n";
    echo "\nTo create an admin user, run:\n";
    echo "  php artisan make:filament-user\n\n";
    exit;
}

// List all users with their roles
echo "Users in database:\n";
echo "==========================================\n";

$users = User::with('roles')->get();

foreach ($users as $user) {
    echo "\n";
    echo "ID: {$user->id}\n";
    echo "Name: {$user->name}\n";
    echo "Email: {$user->email}\n";

    if ($user->roles->count() > 0) {
        echo "Roles: " . $user->roles->pluck('name')->join(', ') . "\n";
    } else {
        echo "Roles: (none)\n";
    }

    // Check if super admin
    if ($user->hasRole('super_admin')) {
        echo "✓ This is a SUPER ADMIN\n";
    }

    echo "Created: {$user->created_at->format('Y-m-d H:i:s')}\n";
}

echo "\n==========================================\n";

// Check available roles
echo "\nAvailable roles:\n";
$roles = Role::all();
if ($roles->count() > 0) {
    foreach ($roles as $role) {
        $userCount = $role->users()->count();
        echo "  • {$role->name} ({$userCount} users)\n";
    }
} else {
    echo "  (no roles found)\n";
}

echo "\n==========================================\n";

// Check for super_admin users
$superAdmins = User::role('super_admin')->get();
if ($superAdmins->count() > 0) {
    echo "\n✓ Super Admin accounts found:\n";
    foreach ($superAdmins as $admin) {
        echo "  • {$admin->name} ({$admin->email})\n";
    }
} else {
    echo "\n❌ No super_admin users found!\n";
    echo "\nTo assign super_admin role to a user:\n";
    echo "  php artisan tinker\n";
    echo "  \$user = App\\Models\\User::first();\n";
    echo "  \$user->assignRole('super_admin');\n";
}

echo "\n==========================================\n\n";
