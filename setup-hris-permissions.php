<?php

/**
 * HRIS Permissions Setup Script
 *
 * Run this with: php setup-hris-permissions.php
 *
 * This script:
 * 1. Generates all Shield permissions
 * 2. Assigns them to super_admin and hr-admin roles
 * 3. Clears caches
 * 4. Verifies the setup
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "==========================================\n";
echo "HRIS Permissions Setup\n";
echo "==========================================\n\n";

// Step 1: Generate permissions
echo "Step 1: Generating Shield permissions...\n";
Artisan::call('shield:generate', ['--all' => true]);
echo Artisan::output();

// Step 2: Get or create roles
echo "\nStep 2: Setting up roles...\n";

$superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$hrAdmin = Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
$hrStaff = Role::firstOrCreate(['name' => 'hr-staff', 'guard_name' => 'web']);

echo "✓ Roles ready: super_admin, hr-admin, hr-staff\n";

// Step 3: Assign permissions
echo "\nStep 3: Assigning permissions...\n";

// Get all HRIS-related permissions
$hrisPermissions = Permission::where(function($query) {
    $query->where('name', 'like', '%attendance%')
          ->orWhere('name', 'like', '%salary%')
          ->orWhere('name', 'like', '%payrun%')
          ->orWhere('name', 'like', '%reimbursement%')
          ->orWhere('name', 'like', '%job%')
          ->orWhere('name', 'like', '%applicant%')
          ->orWhere('name', 'like', '%interview%')
          ->orWhere('name', 'like', '%performance%')
          ->orWhere('name', 'like', '%training%')
          ->orWhere('name', 'like', '%approval%');
})->get();

echo "Found {$hrisPermissions->count()} HRIS permissions\n";

// Assign to super_admin (all permissions)
$allPermissions = Permission::all();
$superAdmin->syncPermissions($allPermissions);
echo "✓ Assigned all permissions to super_admin\n";

// Assign HRIS permissions to hr-admin
$hrAdmin->syncPermissions($hrisPermissions);
echo "✓ Assigned HRIS permissions to hr-admin\n";

// Assign view-only HRIS permissions to hr-staff
$viewPermissions = $hrisPermissions->filter(function($permission) {
    return str_contains($permission->name, 'view');
});
$hrStaff->syncPermissions($viewPermissions);
echo "✓ Assigned view permissions to hr-staff\n";

// Step 4: Clear caches
echo "\nStep 4: Clearing caches...\n";
Artisan::call('permission:cache-reset');
Artisan::call('cache:clear');
Artisan::call('config:clear');
Artisan::call('route:clear');
echo "✓ Caches cleared\n";

// Step 5: Verify
echo "\nStep 5: Verification...\n";
echo "==========================================\n";

$resources = [
    'Attendance Log' => 'attendance::log',
    'Salary Structure' => 'salary::structure',
    'Payrun Batch' => 'payrun::batch',
    'Reimbursement' => 'reimbursement',
    'Job Posting' => 'job::posting',
    'Applicant' => 'applicant',
    'Interview' => 'interview',
    'Performance Cycle' => 'performance::cycle',
    'Performance Goal' => 'performance::goal',
    'Performance Review' => 'performance::review',
    'Training Record' => 'training::record',
];

foreach ($resources as $label => $resource) {
    $count = Permission::where('name', 'like', "%{$resource}%")->count();
    $status = $count > 0 ? '✓' : '✗';
    echo "{$status} {$label}: {$count} permissions\n";
}

echo "\n==========================================\n";
echo "Setup Complete!\n";
echo "==========================================\n\n";

echo "Next steps:\n";
echo "1. Log out of Filament\n";
echo "2. Log back in\n";
echo "3. You should now see these menu groups:\n";
echo "   • HRIS (with Attendance Logs)\n";
echo "   • Payroll\n";
echo "   • Recruitment\n";
echo "   • Performance\n";
echo "   • Training\n\n";

echo "If menus still don't appear:\n";
echo "1. Check your user has super_admin role\n";
echo "2. Run: php artisan shield:super-admin\n";
echo "3. Clear browser cache (Ctrl+Shift+R)\n\n";
