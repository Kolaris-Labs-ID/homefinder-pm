<?php

/**
 * HRIS Permissions Fix Script
 * Run: php fix-hris-permissions.php
 *
 * This script forcefully creates all HRIS permissions and assigns them to super_admin
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

echo "==========================================\n";
echo "HRIS Permissions Fix Script\n";
echo "==========================================\n\n";

// Define all HRIS resources and their permission names
$resources = [
    // Phase 2
    'attendance::log' => 'AttendanceLog',
    'approval::workflow' => 'ApprovalWorkflow',

    // Phase 3
    'salary::structure' => 'SalaryStructure',
    'payrun::batch' => 'PayrunBatch',
    'payroll::record' => 'PayrollRecord',
    'reimbursement' => 'Reimbursement',

    // Phase 4 - Recruitment
    'job::posting' => 'JobPosting',
    'applicant' => 'Applicant',
    'interview' => 'Interview',

    // Phase 4 - Performance
    'performance::cycle' => 'PerformanceCycle',
    'performance::goal' => 'PerformanceGoal',
    'performance::review' => 'PerformanceReview',

    // Phase 4 - Training
    'training::record' => 'TrainingRecord',
];

$permissionPrefixes = [
    'view',
    'view_any',
    'create',
    'update',
    'delete',
    'delete_any',
    'restore',
    'restore_any',
    'replicate',
    'reorder',
    'force_delete',
    'force_delete_any',
];

echo "Step 1: Creating permissions...\n";
$created = 0;
$existing = 0;

foreach ($resources as $resourceKey => $resourceName) {
    foreach ($permissionPrefixes as $prefix) {
        $permissionName = "{$prefix}_{$resourceKey}";

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName, 'guard_name' => 'web']
        );

        if ($permission->wasRecentlyCreated) {
            $created++;
            echo "  + Created: {$permissionName}\n";
        } else {
            $existing++;
        }
    }
}

echo "\n✓ Permissions: {$created} created, {$existing} already existed\n";

// Step 2: Ensure super_admin role exists
echo "\nStep 2: Ensuring super_admin role exists...\n";
$superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
echo "✓ super_admin role ready\n";

// Step 3: Assign ALL permissions to super_admin
echo "\nStep 3: Assigning all permissions to super_admin...\n";
$allPermissions = Permission::all();
$superAdmin->syncPermissions($allPermissions);
echo "✓ Assigned {$allPermissions->count()} permissions to super_admin\n";

// Step 4: Ensure first user has super_admin role
echo "\nStep 4: Checking user roles...\n";
$firstUser = User::first();
if ($firstUser) {
    if (!$firstUser->hasRole('super_admin')) {
        $firstUser->assignRole('super_admin');
        echo "✓ Assigned super_admin role to {$firstUser->email}\n";
    } else {
        echo "✓ {$firstUser->email} already has super_admin role\n";
    }
}

// Step 5: Clear all caches
echo "\nStep 5: Clearing caches...\n";
try {
    Artisan::call('permission:cache-reset');
    echo "✓ Permission cache reset\n";
} catch (\Exception $e) {
    echo "⚠ Permission cache: {$e->getMessage()}\n";
}

try {
    Artisan::call('cache:clear');
    echo "✓ Application cache cleared\n";
} catch (\Exception $e) {
    echo "⚠ Cache clear: {$e->getMessage()}\n";
}

try {
    Artisan::call('config:clear');
    echo "✓ Config cache cleared\n";
} catch (\Exception $e) {
    echo "⚠ Config clear: {$e->getMessage()}\n";
}

try {
    Artisan::call('view:clear');
    echo "✓ View cache cleared\n";
} catch (\Exception $e) {
    echo "⚠ View clear: {$e->getMessage()}\n";
}

try {
    Artisan::call('route:clear');
    echo "✓ Route cache cleared\n";
} catch (\Exception $e) {
    echo "⚠ Route clear: {$e->getMessage()}\n";
}

// Step 6: Verification
echo "\n==========================================\n";
echo "VERIFICATION\n";
echo "==========================================\n";

$verifyPermissions = [
    'view_any_attendance::log',
    'view_any_salary::structure',
    'view_any_job::posting',
    'view_any_performance::cycle',
    'view_any_training::record',
];

foreach ($verifyPermissions as $perm) {
    $exists = Permission::where('name', $perm)->exists();
    $hasIt = $superAdmin->hasPermissionTo($perm);
    $status = ($exists && $hasIt) ? '✓' : '✗';
    echo "{$status} {$perm}\n";
}

echo "\n==========================================\n";
echo "DONE!\n";
echo "==========================================\n";
echo "\nNext steps:\n";
echo "1. Log out of Filament completely\n";
echo "2. Clear your browser cache (Ctrl+Shift+Delete)\n";
echo "3. Log back in\n";
echo "4. Check for new menu groups: HRIS, Payroll, Recruitment, Performance, Training\n\n";

// Additional: Check if OPcache is enabled
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OPcache has been reset\n";
} else {
    echo "Note: OPcache reset not available (may need manual server restart)\n";
}

echo "\n";
