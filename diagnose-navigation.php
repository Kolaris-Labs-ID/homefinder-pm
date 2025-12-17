<?php

/**
 * Diagnostic script to identify why Filament navigation items are not showing
 * Run with: php diagnose-navigation.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;
use App\Models\User;

echo "==========================================\n";
echo "Filament Navigation Diagnostic\n";
echo "==========================================\n\n";

// Get the admin user
$user = User::where('email', 'admin@partnershub.co')->first();
if (!$user) {
    echo "ERROR: User admin@partnershub.co not found!\n";
    exit(1);
}

echo "User: {$user->name} ({$user->email})\n";
echo "Roles: " . $user->roles->pluck('name')->join(', ') . "\n\n";

// Check specific permissions
$permissions = [
    'view_any_attendance::log',
    'view_any_salary::structure',
    'view_any_payrun::batch',
    'view_any_reimbursement',
    'view_any_job::posting',
    'view_any_applicant',
    'view_any_performance::goal',
    'view_any_training::record',
];

echo "Permission Checks:\n";
echo "==========================================\n";
foreach ($permissions as $perm) {
    $exists = Permission::where('name', $perm)->exists();
    $canDo = $user->can($perm);
    $status = $canDo ? '✓' : '✗';
    $existsStatus = $exists ? 'exists' : 'MISSING';
    echo "{$status} {$perm} ({$existsStatus})\n";
}

echo "\n==========================================\n";
echo "Filament Resources:\n";
echo "==========================================\n";

// Get all registered resources
$resources = Filament::getResources();
$targetResources = [
    'AttendanceLogResource',
    'SalaryStructureResource',
    'PayrunBatchResource',
    'ReimbursementResource',
    'JobPostingResource',
    'ApplicantResource',
    'PerformanceGoalResource',
    'TrainingRecordResource',
];

foreach ($resources as $resource) {
    $shortName = class_basename($resource);
    if (in_array($shortName, $targetResources) || str_contains($shortName, 'Employee')) {
        $navGroup = $resource::getNavigationGroup() ?? 'None';
        $navLabel = $resource::getNavigationLabel() ?? $shortName;
        $shouldRegister = $resource::shouldRegisterNavigation() ? 'Yes' : 'No';

        // Check if user can access
        $model = $resource::getModel();
        $canViewAny = false;
        try {
            $canViewAny = $user->can('viewAny', $model);
        } catch (\Exception $e) {
            $canViewAny = 'Error: ' . $e->getMessage();
        }

        echo "\n{$shortName}:\n";
        echo "  Navigation Group: {$navGroup}\n";
        echo "  Navigation Label: {$navLabel}\n";
        echo "  Should Register Nav: {$shouldRegister}\n";
        echo "  User Can ViewAny: " . (is_bool($canViewAny) ? ($canViewAny ? 'Yes' : 'No') : $canViewAny) . "\n";
    }
}

echo "\n==========================================\n";
echo "Navigation Groups Found:\n";
echo "==========================================\n";

$groups = [];
foreach ($resources as $resource) {
    $group = $resource::getNavigationGroup() ?? 'Ungrouped';
    if (!isset($groups[$group])) {
        $groups[$group] = [];
    }
    $groups[$group][] = class_basename($resource);
}

foreach ($groups as $group => $items) {
    echo "\n{$group}:\n";
    foreach ($items as $item) {
        echo "  - {$item}\n";
    }
}

echo "\n==========================================\n";
echo "Gate::before Check:\n";
echo "==========================================\n";

// Test if Gate::before is working
auth()->login($user);
$testPerm = 'view_any_attendance::log';
$result = Gate::allows($testPerm);
echo "Gate::allows('{$testPerm}'): " . ($result ? 'Yes' : 'No') . "\n";

// Check if super_admin bypass is working
$isSuperAdmin = $user->hasRole('super_admin');
echo "User has super_admin role: " . ($isSuperAdmin ? 'Yes' : 'No') . "\n";

echo "\n==========================================\n";
echo "Diagnostic Complete\n";
echo "==========================================\n";
