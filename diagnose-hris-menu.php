<?php

/**
 * HRIS Menu Diagnostic Script
 * Run: php diagnose-hris-menu.php
 *
 * This script checks why HRIS Phase 2,3,4 menus are not showing
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

echo "==========================================\n";
echo "HRIS Menu Diagnostic Report\n";
echo "==========================================\n\n";

// 1. Check database tables exist
echo "1. DATABASE TABLES CHECK\n";
echo "-----------------------------------------\n";
$tables = [
    'attendance_logs' => 'Phase 2',
    'salary_structures' => 'Phase 3',
    'payrun_batches' => 'Phase 3',
    'reimbursements' => 'Phase 3',
    'job_postings' => 'Phase 4',
    'applicants' => 'Phase 4',
    'interviews' => 'Phase 4',
    'performance_cycles' => 'Phase 4',
    'performance_goals' => 'Phase 4',
    'performance_reviews' => 'Phase 4',
    'training_records' => 'Phase 4',
];

foreach ($tables as $table => $phase) {
    try {
        $exists = DB::getSchemaBuilder()->hasTable($table);
        $status = $exists ? '✓' : '✗';
        echo "{$status} {$table} ({$phase})\n";
    } catch (\Exception $e) {
        echo "✗ {$table} - Error: {$e->getMessage()}\n";
    }
}

// 2. Check permissions exist
echo "\n2. PERMISSIONS CHECK\n";
echo "-----------------------------------------\n";
$requiredPermissions = [
    'view_any_attendance::log' => 'Attendance Logs',
    'view_any_salary::structure' => 'Salary Structures',
    'view_any_payrun::batch' => 'Payrun Batches',
    'view_any_reimbursement' => 'Reimbursements',
    'view_any_job::posting' => 'Job Postings',
    'view_any_applicant' => 'Applicants',
    'view_any_interview' => 'Interviews',
    'view_any_performance::cycle' => 'Performance Cycles',
    'view_any_performance::goal' => 'Performance Goals',
    'view_any_performance::review' => 'Performance Reviews',
    'view_any_training::record' => 'Training Records',
];

$missingPermissions = [];
foreach ($requiredPermissions as $permission => $label) {
    $exists = Permission::where('name', $permission)->exists();
    $status = $exists ? '✓' : '✗';
    echo "{$status} {$permission} ({$label})\n";
    if (!$exists) {
        $missingPermissions[] = $permission;
    }
}

// 3. Check roles
echo "\n3. ROLES CHECK\n";
echo "-----------------------------------------\n";
$roles = Role::all();
foreach ($roles as $role) {
    $permCount = $role->permissions()->count();
    echo "- {$role->name}: {$permCount} permissions\n";
}

// 4. Check super_admin role permissions
echo "\n4. SUPER_ADMIN PERMISSIONS FOR HRIS\n";
echo "-----------------------------------------\n";
$superAdmin = Role::where('name', 'super_admin')->first();
if ($superAdmin) {
    foreach ($requiredPermissions as $permission => $label) {
        $has = $superAdmin->hasPermissionTo($permission);
        $status = $has ? '✓' : '✗';
        echo "{$status} {$permission}\n";
    }
} else {
    echo "✗ super_admin role not found!\n";
}

// 5. Check current user (if logged in via session)
echo "\n5. USER CHECK\n";
echo "-----------------------------------------\n";
$users = User::with('roles')->get();
echo "Total users: " . $users->count() . "\n";
foreach ($users->take(5) as $user) {
    $roleNames = $user->roles->pluck('name')->implode(', ') ?: 'No roles';
    echo "- {$user->name} ({$user->email}): {$roleNames}\n";
}

// 6. Summary and recommendations
echo "\n==========================================\n";
echo "SUMMARY & RECOMMENDATIONS\n";
echo "==========================================\n";

if (count($missingPermissions) > 0) {
    echo "\n⚠ MISSING PERMISSIONS DETECTED!\n";
    echo "Run these commands on production:\n\n";
    echo "php artisan shield:generate --all\n";
    echo "php artisan shield:super-admin\n";
    echo "php artisan permission:cache-reset\n";
    echo "php artisan cache:clear\n";
    echo "php artisan config:clear\n";
    echo "php artisan view:clear\n";
} else {
    echo "\n✓ All permissions exist in database.\n";
    echo "\nIf menus still don't show, check:\n";
    echo "1. Your user has super_admin role assigned\n";
    echo "2. Try logging out and back in\n";
    echo "3. Check if OPcache needs clearing on server\n";
}

echo "\n==========================================\n";
echo "Total permissions in database: " . Permission::count() . "\n";
echo "==========================================\n\n";
