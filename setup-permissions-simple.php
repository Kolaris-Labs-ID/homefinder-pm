<?php

/**
 * Simple HRIS Permissions Setup (Non-Interactive)
 * Run: php setup-permissions-simple.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "==========================================\n";
echo "HRIS Permissions Setup (Simple)\n";
echo "==========================================\n\n";

try {
    // Step 1: Generate permissions using --option flag to avoid interaction
    echo "Step 1: Generating Shield permissions...\n";
    Artisan::call('shield:generate', [
        '--all' => true,
        '--option' => '1', // Auto-select first option
    ]);
    echo "✓ Permissions generated\n";

} catch (\Exception $e) {
    echo "Note: shield:generate may have issues. Continuing...\n";
}

// Step 2: Manually ensure roles exist
echo "\nStep 2: Ensuring roles exist...\n";
$superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$hrAdmin = Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
echo "✓ Roles ready\n";

// Step 3: Assign ALL permissions to super_admin
echo "\nStep 3: Assigning permissions...\n";
$allPermissions = Permission::all();
$superAdmin->syncPermissions($allPermissions);
echo "✓ Assigned {$allPermissions->count()} permissions to super_admin\n";

// Step 4: Clear caches
echo "\nStep 4: Clearing caches...\n";
Artisan::call('permission:cache-reset');
Artisan::call('cache:clear');
echo "✓ Caches cleared\n";

// Step 5: Verify
echo "\nStep 5: Verification...\n";
echo "==========================================\n";

$resources = [
    'Attendance' => 'attendance',
    'Salary' => 'salary',
    'Payrun' => 'payrun',
    'Reimbursement' => 'reimbursement',
    'Job Posting' => 'job',
    'Applicant' => 'applicant',
    'Performance' => 'performance',
    'Training' => 'training',
];

$totalFound = 0;
foreach ($resources as $label => $keyword) {
    $count = Permission::where('name', 'like', "%{$keyword}%")->count();
    $status = $count > 0 ? '✓' : '✗';
    echo "{$status} {$label}: {$count} permissions\n";
    $totalFound += $count;
}

echo "\n==========================================\n";
if ($totalFound > 0) {
    echo "✓ Setup Complete! Found {$totalFound} HRIS permissions\n";
} else {
    echo "⚠ Warning: No HRIS permissions found!\n";
    echo "You may need to run: php artisan shield:generate --all\n";
}
echo "==========================================\n\n";

echo "Next steps:\n";
echo "1. Log out of Filament\n";
echo "2. Log back in\n";
echo "3. Check for new menu groups\n\n";
