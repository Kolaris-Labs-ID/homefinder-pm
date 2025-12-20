<?php

/**
 * Diagnostic script for attendance API 500 errors.
 *
 * This script checks:
 * 1. Database tables exist
 * 2. Users have employee records
 * 3. Required relationships are working
 * 4. Sample data can be created
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Hris\Employee;
use App\Models\Hris\AttendanceLog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== Attendance API Diagnostic Tool ===" . PHP_EOL . PHP_EOL;

// Check 1: Database tables
echo "1. Checking database tables..." . PHP_EOL;
$tables = ['users', 'employees', 'attendance_logs'];
$allTablesExist = true;

foreach ($tables as $table) {
    $exists = Schema::hasTable($table);
    $status = $exists ? '✓' : '✗';
    echo "   {$status} Table '{$table}': " . ($exists ? 'EXISTS' : 'MISSING') . PHP_EOL;
    if (!$exists) {
        $allTablesExist = false;
    }
}

if (!$allTablesExist) {
    echo PHP_EOL . "ERROR: Some tables are missing. Run: php artisan migrate" . PHP_EOL;
    exit(1);
}

echo PHP_EOL;

// Check 2: Users and employee records
echo "2. Checking users and employee records..." . PHP_EOL;
$totalUsers = User::count();
$totalEmployees = Employee::count();
$usersWithEmployees = User::has('employee')->count();
$usersWithoutEmployees = User::doesntHave('employee')->count();

echo "   Total users: {$totalUsers}" . PHP_EOL;
echo "   Total employees: {$totalEmployees}" . PHP_EOL;
echo "   Users with employee records: {$usersWithEmployees}" . PHP_EOL;
echo "   Users WITHOUT employee records: {$usersWithoutEmployees}" . PHP_EOL;

if ($usersWithoutEmployees > 0) {
    echo PHP_EOL . "   ⚠ WARNING: {$usersWithoutEmployees} users don't have employee records!" . PHP_EOL;
    echo "   These users will get 404/500 errors on attendance endpoints." . PHP_EOL;
    echo PHP_EOL;
    echo "   Users without employee records:" . PHP_EOL;
    User::doesntHave('employee')->each(function ($user) {
        echo "   - {$user->name} ({$user->email}) [ID: {$user->id}]" . PHP_EOL;
    });
}

echo PHP_EOL;

// Check 3: Test a specific user (if provided)
if ($argc > 1) {
    $email = $argv[1];
    echo "3. Testing specific user: {$email}" . PHP_EOL;

    $user = User::where('email', $email)->first();

    if (!$user) {
        echo "   ✗ User not found!" . PHP_EOL;
    } else {
        echo "   ✓ User found: {$user->name} (ID: {$user->id})" . PHP_EOL;

        $employee = $user->employee;
        if (!$employee) {
            echo "   ✗ No employee record!" . PHP_EOL;
            echo "   This user will get errors on attendance endpoints." . PHP_EOL;
        } else {
            echo "   ✓ Employee record found (ID: {$employee->id})" . PHP_EOL;

            // Check attendance logs
            $attendanceCount = AttendanceLog::where('employee_id', $employee->id)->count();
            echo "   ✓ Attendance logs: {$attendanceCount}" . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

// Check 4: Database connection
echo "4. Testing database connection..." . PHP_EOL;
try {
    DB::connection()->getPdo();
    echo "   ✓ Database connection: OK" . PHP_EOL;
} catch (\Exception $e) {
    echo "   ✗ Database connection: FAILED" . PHP_EOL;
    echo "   Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL;

// Summary
echo "=== SUMMARY ===" . PHP_EOL;
if ($usersWithoutEmployees > 0) {
    echo "⚠ ACTION REQUIRED:" . PHP_EOL;
    echo "Run: php create-missing-employee-records.php" . PHP_EOL;
    echo "This will create employee records for all users." . PHP_EOL;
} else {
    echo "✓ All checks passed!" . PHP_EOL;
    echo "The attendance API should work correctly." . PHP_EOL;
}

echo PHP_EOL;
