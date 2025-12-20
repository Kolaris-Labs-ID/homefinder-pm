<?php

/**
 * Script to create missing employee records for users without them.
 *
 * This fixes the 500 errors on attendance API endpoints by ensuring
 * all users have corresponding employee records.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Hris\Employee;
use Illuminate\Support\Facades\DB;

echo "=== Creating Missing Employee Records ===" . PHP_EOL . PHP_EOL;

// Find all users without employee records
$usersWithoutEmployees = User::whereDoesntHave('employee')->get();

echo "Found {$usersWithoutEmployees->count()} users without employee records" . PHP_EOL . PHP_EOL;

if ($usersWithoutEmployees->isEmpty()) {
    echo "✓ All users already have employee records!" . PHP_EOL;
    exit(0);
}

DB::beginTransaction();

try {
    foreach ($usersWithoutEmployees as $user) {
        echo "Creating employee record for: {$user->name} ({$user->email})" . PHP_EOL;

        $employee = Employee::create([
            'user_id' => $user->id,
            'nik' => null, // Will be filled in later by HR
            'hire_date' => now(), // Default to today, can be updated later
            'employment_status' => 'permanent',
            'is_active' => true,
        ]);

        echo "  ✓ Created employee ID: {$employee->id}" . PHP_EOL;
    }

    DB::commit();

    echo PHP_EOL . "=== SUCCESS ===" . PHP_EOL;
    echo "Created {$usersWithoutEmployees->count()} employee records" . PHP_EOL;
    echo PHP_EOL . "You can now:" . PHP_EOL;
    echo "1. Test the attendance API endpoints" . PHP_EOL;
    echo "2. Update employee details (NIK, hire_date, etc.) in the admin panel" . PHP_EOL;

} catch (\Exception $e) {
    DB::rollBack();
    echo PHP_EOL . "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
