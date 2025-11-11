#!/usr/bin/env php
<?php

/**
 * Fix 403 Error for Google OAuth Users
 * 
 * This script assigns the 'panel_user' role to all users who don't have it,
 * which is required by Filament Shield to access the admin panel.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

echo "=== Fixing 403 Error for Google OAuth Users ===\n\n";

// Ensure panel_user role exists
$panelUserRole = Role::firstOrCreate(
    ['name' => 'panel_user', 'guard_name' => 'web']
);
echo "✓ panel_user role exists\n\n";

// Get all users without panel_user role
$usersWithoutPanelAccess = User::whereDoesntHave('roles', function($query) {
    $query->where('name', 'panel_user');
})->get();

echo "Found {$usersWithoutPanelAccess->count()} users without panel_user role\n\n";

if ($usersWithoutPanelAccess->count() > 0) {
    echo "Assigning panel_user role to users:\n";
    echo str_repeat('-', 50) . "\n";
    
    foreach ($usersWithoutPanelAccess as $user) {
        $user->assignRole($panelUserRole);
        echo "✓ {$user->name} ({$user->email})\n";
    }
    
    echo str_repeat('-', 50) . "\n";
    echo "\n✓ Successfully assigned panel_user role to {$usersWithoutPanelAccess->count()} user(s)\n\n";
} else {
    echo "✓ All users already have panel_user role\n\n";
}

// Also ensure all users have at least one role (member, admin, or super_admin)
$usersWithoutAnyRole = User::doesntHave('roles')->get();

if ($usersWithoutAnyRole->count() > 0) {
    echo "Found {$usersWithoutAnyRole->count()} users without any role\n";
    echo "Assigning 'member' role to these users:\n";
    echo str_repeat('-', 50) . "\n";
    
    $memberRole = Role::where('name', 'member')->where('guard_name', 'web')->first();
    
    if (!$memberRole) {
        echo "⚠ Warning: 'member' role not found. Creating it...\n";
        $memberRole = Role::create(['name' => 'member', 'guard_name' => 'web']);
    }
    
    foreach ($usersWithoutAnyRole as $user) {
        $user->assignRole($memberRole);
        echo "✓ {$user->name} ({$user->email}) - assigned 'member' role\n";
    }
    
    echo str_repeat('-', 50) . "\n";
    echo "\n✓ Successfully assigned roles\n\n";
}

echo "=== Fix Complete ===\n";
echo "\nUsers should now be able to access the admin panel at https://pm.partnershub.co/admin\n";

