#!/usr/bin/env php
<?php

/**
 * Create Super Admin User
 * 
 * This script creates a super admin user with all permissions.
 * Run this on your VPS: php create_super_admin.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

echo "=== Create Super Admin User ===\n\n";

// Ensure roles and permissions exist
echo "1. Checking roles and permissions...\n";
echo "----------------------------------------\n";

// Run RoleSeeder to ensure all roles exist
$seeder = new \Database\Seeders\RoleSeeder();
$seeder->run();
echo "✓ Roles and permissions initialized\n\n";

// Get or create super_admin role
$superAdminRole = Role::firstOrCreate(
    ['name' => 'super_admin', 'guard_name' => 'web']
);

// Get or create panel_user role
$panelUserRole = Role::firstOrCreate(
    ['name' => 'panel_user', 'guard_name' => 'web']
);

echo "2. Creating super admin user...\n";
echo "----------------------------------------\n";

// Get user input
echo "Enter email address: ";
$email = trim(fgets(STDIN));

if (empty($email)) {
    echo "❌ Email is required!\n";
    exit(1);
}

// Check if user already exists
$existingUser = User::where('email', $email)->first();

if ($existingUser) {
    echo "⚠ User with email '{$email}' already exists.\n";
    echo "Do you want to make this user a super admin? (y/n): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }
    
    $user = $existingUser;
    echo "✓ Using existing user: {$user->name}\n";
} else {
    echo "Enter name: ";
    $name = trim(fgets(STDIN));
    
    if (empty($name)) {
        echo "❌ Name is required!\n";
        exit(1);
    }
    
    echo "Enter password: ";
    $password = trim(fgets(STDIN));
    
    if (empty($password)) {
        echo "❌ Password is required!\n";
        exit(1);
    }
    
    // Create new user
    $user = User::create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make($password),
    ]);
    
    echo "✓ User created: {$user->name} ({$user->email})\n";
}

// Assign super_admin role
if (!$user->hasRole('super_admin')) {
    $user->assignRole($superAdminRole);
    echo "✓ Assigned 'super_admin' role\n";
} else {
    echo "✓ User already has 'super_admin' role\n";
}

// Assign panel_user role (required for Filament access)
if (!$user->hasRole('panel_user')) {
    $user->assignRole($panelUserRole);
    echo "✓ Assigned 'panel_user' role\n";
} else {
    echo "✓ User already has 'panel_user' role\n";
}

// Sync all permissions to super_admin role (ensure it has all permissions)
$superAdminRole->syncPermissions(\Spatie\Permission\Models\Permission::all());
echo "✓ Synced all permissions to super_admin role\n";

echo "\n=== Success ===\n";
echo "Super admin user created/updated:\n";
echo "  Name: {$user->name}\n";
echo "  Email: {$user->email}\n";
echo "  Roles: " . $user->roles->pluck('name')->join(', ') . "\n";
echo "\nYou can now log in at: https://pm.partnershub.co/admin\n";


