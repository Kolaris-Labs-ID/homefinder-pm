<?php

/**
 * Fix Department and Position Create Permissions
 * Run: php fix-department-position-permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

echo "==========================================\n";
echo "Fix Department & Position Permissions\n";
echo "==========================================\n\n";

// Define the permissions needed for Department and Position
$permissions = [
    // Department permissions
    'view_any_department',
    'view_department',
    'create_department',
    'update_department',
    'delete_department',
    'delete_any_department',
    'force_delete_department',
    'force_delete_any_department',
    'restore_department',
    'restore_any_department',
    'replicate_department',
    'reorder_department',

    // Position permissions
    'view_any_position',
    'view_position',
    'create_position',
    'update_position',
    'delete_position',
    'delete_any_position',
    'force_delete_position',
    'force_delete_any_position',
    'restore_position',
    'restore_any_position',
    'replicate_position',
    'reorder_position',
];

echo "Creating permissions...\n";
foreach ($permissions as $permission) {
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    echo "  ✓ {$permission}\n";
}

// Assign to super_admin role
echo "\nAssigning to super_admin role...\n";
$superAdmin = Role::where('name', 'super_admin')->first();
if ($superAdmin) {
    $superAdmin->givePermissionTo($permissions);
    echo "✓ Assigned to super_admin\n";
} else {
    echo "⚠ super_admin role not found\n";
}

// Also assign to hr-admin if exists
$hrAdmin = Role::where('name', 'hr-admin')->first();
if ($hrAdmin) {
    $hrAdmin->givePermissionTo($permissions);
    echo "✓ Assigned to hr-admin\n";
}

// Clear permission cache
echo "\nClearing cache...\n";
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
\Illuminate\Support\Facades\Artisan::call('cache:clear');
echo "✓ Cache cleared\n";

// Verify
echo "\n==========================================\n";
echo "Verification:\n";
$createDept = Permission::where('name', 'create_department')->exists();
$createPos = Permission::where('name', 'create_position')->exists();
echo "create_department: " . ($createDept ? '✓ exists' : '✗ missing') . "\n";
echo "create_position: " . ($createPos ? '✓ exists' : '✗ missing') . "\n";

// Check if super_admin has them
if ($superAdmin) {
    echo "\nsuper_admin has create_department: " . ($superAdmin->hasPermissionTo('create_department') ? '✓' : '✗') . "\n";
    echo "super_admin has create_position: " . ($superAdmin->hasPermissionTo('create_position') ? '✓' : '✗') . "\n";
}

echo "\n==========================================\n";
echo "Done! Please log out and log back in.\n";
echo "==========================================\n";
