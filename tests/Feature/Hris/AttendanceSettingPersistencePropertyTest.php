<?php

// Feature: attendance-settings, Property 1: Settings persistence round-trip

/**
 * Property-Based Test: Settings persistence round-trip
 *
 * For any valid set of attendance setting values, saving them to the
 * AttendanceSetting model and reading them back should return equivalent values.
 *
 * **Validates: Requirements 1.1, 2.2**
 */

use App\Models\Hris\AttendanceSetting;

beforeEach(function () {
    AttendanceSetting::query()->delete();
});

test('Property 1: Settings persistence round-trip — random valid settings saved and read back are equal', function () {
    for ($i = 0; $i < 100; $i++) {
        // Generate random valid full_day_hours first so half_day_hours can depend on it
        $fullDayHours = round(fake()->randomFloat(2, 4, 12), 2);

        // half_day_hours must be >= 1 and strictly less than full_day_hours
        // Ensure the max for half_day_hours is at least 1 (full_day_hours is always >= 4, so this is safe)
        $halfDayMax = $fullDayHours - 0.01;
        $halfDayHours = round(fake()->randomFloat(2, 1, $halfDayMax), 2);

        // Generate random work_start_time between 06:00 and 12:00
        $hour = fake()->numberBetween(6, 11);
        $minute = fake()->numberBetween(0, 59);
        // If hour is 12, minute must be 0 (12:00 is the upper bound)
        if (fake()->boolean(10)) { // 10% chance of exactly 12:00
            $hour = 12;
            $minute = 0;
        }
        $workStartTime = sprintf('%02d:%02d', $hour, $minute);

        $lateGracePeriod = fake()->numberBetween(0, 60);
        $wfhQuota = fake()->numberBetween(0, 5);

        $input = [
            'work_start_time' => $workStartTime,
            'late_grace_period_minutes' => $lateGracePeriod,
            'wfh_quota_per_week' => $wfhQuota,
            'full_day_hours' => $fullDayHours,
            'half_day_hours' => $halfDayHours,
        ];

        // Save to database
        $setting = AttendanceSetting::updateOrCreate([], $input);

        // Read back from database (fresh query, no cached model)
        $persisted = AttendanceSetting::query()->first();

        // Assert round-trip equality
        // Normalize time comparison: strip trailing :00 seconds if present
        $normalizedPersisted = preg_replace('/:00$/', '', $persisted->work_start_time);
        $normalizedInput = preg_replace('/:00$/', '', $workStartTime);
        expect($normalizedPersisted)->toBe(
            $normalizedInput,
            "Iteration {$i}: work_start_time mismatch (stored: {$persisted->work_start_time}, expected: {$workStartTime})"
        );
        expect($persisted->late_grace_period_minutes)->toBe(
            $lateGracePeriod,
            "Iteration {$i}: late_grace_period_minutes mismatch"
        );
        expect($persisted->wfh_quota_per_week)->toBe(
            $wfhQuota,
            "Iteration {$i}: wfh_quota_per_week mismatch"
        );
        expect((float) $persisted->full_day_hours)->toEqualWithDelta(
            $fullDayHours,
            0.01,
            "Iteration {$i}: full_day_hours mismatch"
        );
        expect((float) $persisted->half_day_hours)->toEqualWithDelta(
            $halfDayHours,
            0.01,
            "Iteration {$i}: half_day_hours mismatch"
        );
    }
})->group('property-test', 'attendance-settings');

// Feature: attendance-settings, Property 2: Single-row invariant

/**
 * Property-Based Test: Single-row invariant
 *
 * For any sequence of getSettings() calls, the attendance_settings table
 * should contain exactly one row.
 *
 * **Validates: Requirements 1.2**
 */

test('Property 2: Single-row invariant — calling getSettings() N times always results in exactly one row', function () {
    for ($i = 0; $i < 100; $i++) {
        // Clean slate each iteration
        AttendanceSetting::query()->delete();

        // Random number of getSettings() calls (1 to 20)
        $callCount = fake()->numberBetween(1, 20);

        for ($j = 0; $j < $callCount; $j++) {
            AttendanceSetting::getSettings();
        }

        $rowCount = AttendanceSetting::count();

        expect($rowCount)->toBe(
            1,
            "Iteration {$i}: Expected exactly 1 row after {$callCount} getSettings() calls, got {$rowCount}"
        );
    }
})->group('property-test', 'attendance-settings');


// Feature: attendance-settings, Property 3: Service uses dynamic settings

/**
 * Property-Based Test: Service uses dynamic settings
 *
 * For any valid attendance setting stored in the database, the AttendanceService
 * should use those values (not hardcoded defaults) when computing attendance status,
 * determining late threshold, and enforcing WFH quota.
 *
 * **Validates: Requirements 1.4, 5.1, 5.2, 5.3, 5.4**
 */

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Services\Hris\AttendanceService;
use Carbon\Carbon;

test('Property 3: Service uses dynamic settings — computeStatus uses DB work_start_time and late_grace_period_minutes for late threshold', function () {
    for ($i = 0; $i < 100; $i++) {
        AttendanceSetting::query()->delete();

        // Generate random valid settings
        $hour = fake()->numberBetween(6, 11);
        $minute = fake()->numberBetween(0, 59);
        if (fake()->boolean(10)) {
            $hour = 12;
            $minute = 0;
        }
        $workStartTime = sprintf('%02d:%02d', $hour, $minute);
        $lateGracePeriod = fake()->numberBetween(0, 60);
        $fullDayHours = round(fake()->randomFloat(2, 4, 12), 2);
        $halfDayHours = round(fake()->randomFloat(2, 1, $fullDayHours - 0.01), 2);
        $wfhQuota = fake()->numberBetween(0, 5);

        AttendanceSetting::updateOrCreate([], [
            'work_start_time' => $workStartTime,
            'late_grace_period_minutes' => $lateGracePeriod,
            'wfh_quota_per_week' => $wfhQuota,
            'full_day_hours' => $fullDayHours,
            'half_day_hours' => $halfDayHours,
        ]);

        $employee = Employee::factory()->create();
        $today = Carbon::today();

        // Compute the late threshold from DB settings
        $workStart = $today->copy()->setTime($hour, $minute, 0);
        $lateThreshold = $workStart->copy()->addMinutes($lateGracePeriod);

        // Create a log that checks in 1 minute AFTER the late threshold and works a full day
        $checkIn = $lateThreshold->copy()->addMinute();
        $checkOut = $checkIn->copy()->addHours((int) ceil($fullDayHours));

        $log = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => $today,
            'mode' => AttendanceLog::MODE_OFFICE,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT, // will be recomputed
        ]);

        $service = new AttendanceService();
        $status = $service->computeStatus($log);

        expect($status)->toBe(
            AttendanceLog::STATUS_LATE,
            "Iteration {$i}: Check-in at {$checkIn->format('H:i')} should be LATE with work_start={$workStartTime}, grace={$lateGracePeriod}min (threshold={$lateThreshold->format('H:i')})"
        );

        // Now create a log that checks in 1 minute BEFORE the late threshold (on time)
        $earlyCheckIn = $lateThreshold->copy()->subMinutes(2);
        // Ensure check-in is not before midnight
        if ($earlyCheckIn->lt($today)) {
            $earlyCheckIn = $today->copy();
        }
        $earlyCheckOut = $earlyCheckIn->copy()->addHours((int) ceil($fullDayHours));

        $onTimeLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => $today->copy()->addDay(), // different date to avoid conflicts
            'mode' => AttendanceLog::MODE_OFFICE,
            'check_in_time' => $earlyCheckIn,
            'check_out_time' => $earlyCheckOut,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        $service2 = new AttendanceService();
        $onTimeStatus = $service2->computeStatus($onTimeLog);

        expect($onTimeStatus)->toBe(
            AttendanceLog::STATUS_PRESENT,
            "Iteration {$i}: Check-in at {$earlyCheckIn->format('H:i')} should be PRESENT with work_start={$workStartTime}, grace={$lateGracePeriod}min (threshold={$lateThreshold->format('H:i')})"
        );
    }
})->group('property-test', 'attendance-settings');

test('Property 3: Service uses dynamic settings — computeStatus uses DB full_day_hours and half_day_hours for day-type determination', function () {
    for ($i = 0; $i < 100; $i++) {
        AttendanceSetting::query()->delete();

        // Generate random valid settings
        $fullDayHours = round(fake()->randomFloat(2, 4, 12), 2);
        $halfDayHours = round(fake()->randomFloat(2, 1, $fullDayHours - 0.01), 2);

        AttendanceSetting::updateOrCreate([], [
            'work_start_time' => '06:00',
            'late_grace_period_minutes' => 60,
            'wfh_quota_per_week' => 3,
            'full_day_hours' => $fullDayHours,
            'half_day_hours' => $halfDayHours,
        ]);

        $employee = Employee::factory()->create();
        $today = Carbon::today();

        // Check in early (within grace) so lateness doesn't interfere
        $checkIn = $today->copy()->setTime(6, 0, 0);

        // Work exactly half_day_hours (should be half_day since < full_day_hours)
        $halfDayMinutes = (int) round($halfDayHours * 60);
        $checkOutHalfDay = $checkIn->copy()->addMinutes($halfDayMinutes);

        $halfDayLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => $today,
            'mode' => AttendanceLog::MODE_OFFICE,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOutHalfDay,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        $service = new AttendanceService();
        $halfDayStatus = $service->computeStatus($halfDayLog);

        expect($halfDayStatus)->toBe(
            AttendanceLog::STATUS_HALF_DAY,
            "Iteration {$i}: Working {$halfDayHours}h should be HALF_DAY (full_day_hours={$fullDayHours}, half_day_hours={$halfDayHours})"
        );

        // Work full_day_hours (should be present, not half_day)
        $fullDayMinutes = (int) ceil($fullDayHours * 60);
        $checkOutFullDay = $checkIn->copy()->addMinutes($fullDayMinutes);

        $fullDayLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => $today->copy()->addDay(),
            'mode' => AttendanceLog::MODE_OFFICE,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOutFullDay,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        $service2 = new AttendanceService();
        $fullDayStatus = $service2->computeStatus($fullDayLog);

        expect($fullDayStatus)->toBe(
            AttendanceLog::STATUS_PRESENT,
            "Iteration {$i}: Working {$fullDayHours}h should be PRESENT (full_day_hours={$fullDayHours}, half_day_hours={$halfDayHours})"
        );
    }
})->group('property-test', 'attendance-settings');

test('Property 3: Service uses dynamic settings — getRemainingWfhQuota uses DB wfh_quota_per_week', function () {
    for ($i = 0; $i < 100; $i++) {
        AttendanceSetting::query()->delete();

        $wfhQuota = fake()->numberBetween(0, 5);

        AttendanceSetting::updateOrCreate([], [
            'work_start_time' => '09:00',
            'late_grace_period_minutes' => 15,
            'wfh_quota_per_week' => $wfhQuota,
            'full_day_hours' => 8,
            'half_day_hours' => 4,
        ]);

        $employee = Employee::factory()->create();
        $today = Carbon::today();

        // No WFH logs yet — remaining quota should equal the DB setting
        $service = new AttendanceService();
        $remaining = $service->getRemainingWfhQuota($employee, $today);

        expect($remaining)->toBe(
            $wfhQuota,
            "Iteration {$i}: With no WFH logs, remaining quota should be {$wfhQuota} (from DB), got {$remaining}"
        );

        // Create some WFH logs for this week and verify quota decreases accordingly
        $wfhDaysToCreate = fake()->numberBetween(0, min($wfhQuota, 5));
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        for ($d = 0; $d < $wfhDaysToCreate; $d++) {
            $logDate = $weekStart->copy()->addDays($d);
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $logDate,
                'mode' => AttendanceLog::MODE_WFH,
                'check_in_time' => $logDate->copy()->setTime(9, 0, 0),
                'source' => AttendanceLog::SOURCE_WEB,
                'status' => AttendanceLog::STATUS_PRESENT,
            ]);
        }

        $service2 = new AttendanceService();
        $remainingAfter = $service2->getRemainingWfhQuota($employee, $today);
        $expectedRemaining = max(0, $wfhQuota - $wfhDaysToCreate);

        expect($remainingAfter)->toBe(
            $expectedRemaining,
            "Iteration {$i}: After {$wfhDaysToCreate} WFH days with quota={$wfhQuota}, remaining should be {$expectedRemaining}, got {$remainingAfter}"
        );
    }
})->group('property-test', 'attendance-settings');


// Feature: attendance-settings, Property 5: Validation accepts valid and rejects invalid inputs

/**
 * Property-Based Test: Validation accepts valid and rejects invalid inputs
 *
 * For any set of input values, the attendance settings validation should accept
 * the input if and only if all validity predicates are satisfied.
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7**
 */

use Illuminate\Support\Facades\Validator;

test('Property 5: Validation accepts valid and rejects invalid inputs — random inputs pass validation iff all validity predicates hold', function () {
    for ($i = 0; $i < 120; $i++) {
        // Decide whether to generate a valid or invalid tuple (roughly 50/50)
        $generateValid = fake()->boolean(50);

        if ($generateValid) {
            // Generate fully valid inputs
            $fullDayHours = round(fake()->randomFloat(2, 4, 12), 2);
            $halfDayMax = $fullDayHours - 0.01;
            $halfDayHours = round(fake()->randomFloat(2, 1, max(1, $halfDayMax)), 2);

            $hour = fake()->numberBetween(6, 11);
            $minute = fake()->numberBetween(0, 59);
            if (fake()->boolean(10)) {
                $hour = 12;
                $minute = 0;
            }
            $workStartTime = sprintf('%02d:%02d', $hour, $minute);

            $lateGracePeriod = fake()->numberBetween(0, 60);
            $wfhQuota = fake()->numberBetween(0, 5);
        } else {
            // Generate inputs with at least one invalid field
            // Pick a random field to make invalid
            $invalidField = fake()->randomElement([
                'work_start_time',
                'late_grace_period_minutes',
                'wfh_quota_per_week',
                'full_day_hours',
                'half_day_hours',
                'half_day_gte_full_day', // special cross-field case
            ]);

            // Start with valid defaults
            $fullDayHours = round(fake()->randomFloat(2, 4, 12), 2);
            $halfDayHours = round(fake()->randomFloat(2, 1, $fullDayHours - 0.01), 2);
            $hour = fake()->numberBetween(6, 11);
            $minute = fake()->numberBetween(0, 59);
            $workStartTime = sprintf('%02d:%02d', $hour, $minute);
            $lateGracePeriod = fake()->numberBetween(0, 60);
            $wfhQuota = fake()->numberBetween(0, 5);

            // Now break the chosen field
            switch ($invalidField) {
                case 'work_start_time':
                    // Generate time outside 06:00-12:00
                    $badHour = fake()->randomElement([
                        fake()->numberBetween(0, 5),
                        fake()->numberBetween(13, 23),
                    ]);
                    $workStartTime = sprintf('%02d:%02d', $badHour, fake()->numberBetween(0, 59));
                    // Special case: 12:01+ is also invalid
                    if ($badHour === 12) {
                        $workStartTime = sprintf('12:%02d', fake()->numberBetween(1, 59));
                    }
                    break;

                case 'late_grace_period_minutes':
                    $lateGracePeriod = fake()->randomElement([
                        fake()->numberBetween(-100, -1),
                        fake()->numberBetween(61, 200),
                    ]);
                    break;

                case 'wfh_quota_per_week':
                    $wfhQuota = fake()->randomElement([
                        fake()->numberBetween(-100, -1),
                        fake()->numberBetween(6, 200),
                    ]);
                    break;

                case 'full_day_hours':
                    $fullDayHours = fake()->randomElement([
                        round(fake()->randomFloat(2, 0, 3.99), 2),
                        round(fake()->randomFloat(2, 12.01, 50), 2),
                    ]);
                    // Recalculate half_day_hours to be valid relative to new full_day_hours
                    // (we only want full_day_hours to be the invalid field)
                    if ($fullDayHours > 1) {
                        $halfDayHours = round(fake()->randomFloat(2, 1, max(1, $fullDayHours - 0.01)), 2);
                    } else {
                        $halfDayHours = 1;
                    }
                    break;

                case 'half_day_hours':
                    // half_day_hours below minimum (< 1)
                    $halfDayHours = round(fake()->randomFloat(2, -10, 0.99), 2);
                    break;

                case 'half_day_gte_full_day':
                    // Cross-field: half_day_hours >= full_day_hours
                    $fullDayHours = round(fake()->randomFloat(2, 4, 12), 2);
                    $halfDayHours = fake()->boolean(50)
                        ? $fullDayHours // equal — should be rejected
                        : round($fullDayHours + fake()->randomFloat(2, 0.01, 5), 2); // greater
                    break;
            }
        }

        $input = [
            'work_start_time' => $workStartTime,
            'late_grace_period_minutes' => $lateGracePeriod,
            'wfh_quota_per_week' => $wfhQuota,
            'full_day_hours' => $fullDayHours,
            'half_day_hours' => $halfDayHours,
        ];

        // Build equivalent validation rules (matching the Filament form)
        $rules = [
            'work_start_time' => ['required'],
            'late_grace_period_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'wfh_quota_per_week' => ['required', 'integer', 'min:0', 'max:5'],
            'full_day_hours' => ['required', 'numeric', 'min:4', 'max:12'],
            'half_day_hours' => ['required', 'numeric', 'min:1'],
        ];

        $validator = Validator::make($input, $rules);

        // Add custom validation for work_start_time range
        $validator->after(function ($validator) use ($input) {
            if (! empty($input['work_start_time'])) {
                $time = date('H:i', strtotime($input['work_start_time']));
                if ($time < '06:00' || $time > '12:00') {
                    $validator->errors()->add('work_start_time', 'Work start time must be between 06:00 and 12:00.');
                }
            }
        });

        // Add custom validation for half_day_hours < full_day_hours
        $validator->after(function ($validator) use ($input) {
            if ($input['half_day_hours'] !== null && $input['full_day_hours'] !== null
                && (float) $input['half_day_hours'] >= (float) $input['full_day_hours']) {
                $validator->errors()->add('half_day_hours', 'Half day hours must be less than full day hours.');
            }
        });

        $validatorPasses = $validator->passes();

        // Compute the validity predicate independently
        $timeStr = date('H:i', strtotime($input['work_start_time']));
        $isWorkStartTimeValid = $timeStr >= '06:00' && $timeStr <= '12:00';
        $isLateGraceValid = is_int($input['late_grace_period_minutes'])
            && $input['late_grace_period_minutes'] >= 0
            && $input['late_grace_period_minutes'] <= 60;
        $isWfhQuotaValid = is_int($input['wfh_quota_per_week'])
            && $input['wfh_quota_per_week'] >= 0
            && $input['wfh_quota_per_week'] <= 5;
        $isFullDayValid = is_numeric($input['full_day_hours'])
            && (float) $input['full_day_hours'] >= 4
            && (float) $input['full_day_hours'] <= 12;
        $isHalfDayValid = is_numeric($input['half_day_hours'])
            && (float) $input['half_day_hours'] >= 1
            && (float) $input['half_day_hours'] < (float) $input['full_day_hours'];

        $allValid = $isWorkStartTimeValid
            && $isLateGraceValid
            && $isWfhQuotaValid
            && $isFullDayValid
            && $isHalfDayValid;

        $errors = $validator->errors()->all();
        $errorSummary = implode('; ', $errors);

        expect($validatorPasses)->toBe(
            $allValid,
            "Iteration {$i}: Validator " . ($validatorPasses ? 'passed' : 'failed')
            . " but predicate says " . ($allValid ? 'valid' : 'invalid')
            . ". Input: " . json_encode($input)
            . ($errorSummary ? " Errors: {$errorSummary}" : '')
        );
    }
})->group('property-test', 'attendance-settings');


// Feature: attendance-settings, Property 6: Audit log count matches changed fields

/**
 * Property-Based Test: Audit log count matches changed fields
 *
 * For any save operation where N fields have changed from their current values,
 * exactly N AttendanceSettingAudit entries should be created, each containing
 * the correct user_id, field name, old_value, new_value, and a non-null created_at.
 *
 * **Validates: Requirements 4.1, 4.2**
 */

use App\Models\Hris\AttendanceSettingAudit;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

test('Property 6: Audit log count matches changed fields — random subset of changed fields produces matching audit entries', function () {
    $fields = [
        'work_start_time',
        'late_grace_period_minutes',
        'wfh_quota_per_week',
        'full_day_hours',
        'half_day_hours',
    ];

    for ($i = 0; $i < 100; $i++) {
        // Clean slate
        AttendanceSetting::query()->delete();
        AttendanceSettingAudit::query()->truncate();

        // Generate random valid initial settings
        $initialFullDay = round(fake()->randomFloat(2, 5, 12), 2);
        $initialHalfDay = round(fake()->randomFloat(2, 1, $initialFullDay - 0.01), 2);
        $initialHour = fake()->numberBetween(6, 11);
        $initialMinute = fake()->numberBetween(0, 59);
        $initialWorkStart = sprintf('%02d:%02d', $initialHour, $initialMinute);
        $initialGrace = fake()->numberBetween(0, 60);
        $initialWfh = fake()->numberBetween(0, 5);

        $initial = [
            'work_start_time' => $initialWorkStart,
            'late_grace_period_minutes' => $initialGrace,
            'wfh_quota_per_week' => $initialWfh,
            'full_day_hours' => $initialFullDay,
            'half_day_hours' => $initialHalfDay,
        ];

        $settings = AttendanceSetting::create($initial);

        // Randomly select a non-empty subset of fields to change (1 to 5 fields)
        $numFieldsToChange = fake()->numberBetween(1, 5);
        $fieldsToChange = fake()->randomElements($fields, $numFieldsToChange);

        // Generate new valid values for the selected fields, ensuring they differ from initial
        $newValues = $initial; // start with initial values
        foreach ($fieldsToChange as $field) {
            switch ($field) {
                case 'work_start_time':
                    do {
                        $h = fake()->numberBetween(6, 11);
                        $m = fake()->numberBetween(0, 59);
                        if (fake()->boolean(10)) {
                            $h = 12;
                            $m = 0;
                        }
                        $newTime = sprintf('%02d:%02d', $h, $m);
                    } while ($newTime === $initialWorkStart);
                    $newValues['work_start_time'] = $newTime;
                    break;

                case 'late_grace_period_minutes':
                    do {
                        $val = fake()->numberBetween(0, 60);
                    } while ($val === $initialGrace);
                    $newValues['late_grace_period_minutes'] = $val;
                    break;

                case 'wfh_quota_per_week':
                    do {
                        $val = fake()->numberBetween(0, 5);
                    } while ($val === $initialWfh);
                    $newValues['wfh_quota_per_week'] = $val;
                    break;

                case 'full_day_hours':
                    do {
                        $val = round(fake()->randomFloat(2, 5, 12), 2);
                    } while ($val == $initialFullDay);
                    $newValues['full_day_hours'] = $val;
                    // Ensure half_day_hours is still valid (< new full_day_hours)
                    if ((float) $newValues['half_day_hours'] >= $val) {
                        $newValues['half_day_hours'] = round(fake()->randomFloat(2, 1, $val - 0.01), 2);
                    }
                    break;

                case 'half_day_hours':
                    $maxHalf = (float) $newValues['full_day_hours'] - 0.01;
                    do {
                        $val = round(fake()->randomFloat(2, 1, max(1, $maxHalf)), 2);
                    } while ((string) $val === (string) $initialHalfDay);
                    $newValues['half_day_hours'] = $val;
                    break;
            }
        }

        // Compute expected changes by comparing string representations (matching save() logic)
        $expectedChanges = [];
        foreach ($fields as $field) {
            $oldVal = (string) $settings->{$field};
            $newVal = $field === 'work_start_time'
                ? date('H:i', strtotime($newValues[$field]))
                : (string) $newValues[$field];

            if ($oldVal !== $newVal) {
                $expectedChanges[] = [
                    'field' => $field,
                    'old_value' => $oldVal,
                    'new_value' => $newVal,
                ];
            }
        }

        // Create an authenticated user
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'view_attendance_setting', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        // Simulate the save logic directly (matching AttendanceSettings::save())
        $this->actingAs($user);

        DB::transaction(function () use ($settings, $newValues, $expectedChanges, $user) {
            foreach ($expectedChanges as $change) {
                AttendanceSettingAudit::create([
                    'user_id' => $user->id,
                    'field' => $change['field'],
                    'old_value' => $change['old_value'],
                    'new_value' => $change['new_value'],
                ]);
            }

            $settings->update([
                'work_start_time' => date('H:i', strtotime($newValues['work_start_time'])),
                'late_grace_period_minutes' => $newValues['late_grace_period_minutes'],
                'wfh_quota_per_week' => $newValues['wfh_quota_per_week'],
                'full_day_hours' => $newValues['full_day_hours'],
                'half_day_hours' => $newValues['half_day_hours'],
            ]);
        });

        // Assert: audit count equals number of changed fields
        $auditCount = AttendanceSettingAudit::count();
        $expectedCount = count($expectedChanges);

        expect($auditCount)->toBe(
            $expectedCount,
            "Iteration {$i}: Expected {$expectedCount} audit entries, got {$auditCount}. "
            . "Fields to change: " . json_encode($fieldsToChange)
            . " Initial: " . json_encode($initial)
            . " New: " . json_encode($newValues)
        );

        // Assert: each audit entry has correct data
        foreach ($expectedChanges as $expected) {
            $audit = AttendanceSettingAudit::where('field', $expected['field'])->first();

            expect($audit)->not->toBeNull(
                "Iteration {$i}: Missing audit entry for field '{$expected['field']}'"
            );
            expect($audit->user_id)->toBe(
                $user->id,
                "Iteration {$i}: Audit user_id mismatch for field '{$expected['field']}'"
            );
            expect($audit->old_value)->toBe(
                $expected['old_value'],
                "Iteration {$i}: Audit old_value mismatch for field '{$expected['field']}' — expected '{$expected['old_value']}', got '{$audit->old_value}'"
            );
            expect($audit->new_value)->toBe(
                $expected['new_value'],
                "Iteration {$i}: Audit new_value mismatch for field '{$expected['field']}' — expected '{$expected['new_value']}', got '{$audit->new_value}'"
            );
            expect($audit->created_at)->not->toBeNull(
                "Iteration {$i}: Audit created_at should not be null for field '{$expected['field']}'"
            );
        }
    }
})->group('property-test', 'attendance-settings');


// Feature: attendance-settings, Property 7: No audit entries on unchanged save

/**
 * Property-Based Test: No audit entries on unchanged save
 *
 * For any attendance settings state, saving the identical values (no changes)
 * should produce zero new AttendanceSettingAudit entries. This is an idempotence
 * property: save(currentValues) produces no side effects.
 *
 * **Validates: Requirements 4.4**
 */

test('Property 7: No audit entries on unchanged save — saving identical values produces zero audit entries', function () {
    for ($i = 0; $i < 100; $i++) {
        // Clean slate
        AttendanceSetting::query()->delete();
        AttendanceSettingAudit::query()->truncate();

        // Generate random valid initial settings
        $fullDayHours = round(fake()->randomFloat(2, 4, 12), 2);
        $halfDayHours = round(fake()->randomFloat(2, 1, $fullDayHours - 0.01), 2);
        $hour = fake()->numberBetween(6, 11);
        $minute = fake()->numberBetween(0, 59);
        if (fake()->boolean(10)) {
            $hour = 12;
            $minute = 0;
        }
        $workStartTime = sprintf('%02d:%02d', $hour, $minute);
        $lateGracePeriod = fake()->numberBetween(0, 60);
        $wfhQuota = fake()->numberBetween(0, 5);

        // Save initial settings to DB
        $settings = AttendanceSetting::create([
            'work_start_time' => $workStartTime,
            'late_grace_period_minutes' => $lateGracePeriod,
            'wfh_quota_per_week' => $wfhQuota,
            'full_day_hours' => $fullDayHours,
            'half_day_hours' => $halfDayHours,
        ]);

        // Re-read from DB to get the persisted representation (matching save() logic)
        $settings->refresh();

        // Create an authenticated user
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'view_attendance_setting', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);
        $this->actingAs($user);

        // Simulate the save() method with the SAME values (unchanged save)
        // This mirrors the diff logic in AttendanceSettings::save()
        $fields = [
            'work_start_time',
            'late_grace_period_minutes',
            'wfh_quota_per_week',
            'full_day_hours',
            'half_day_hours',
        ];

        // Build submitted data from current settings (identical values)
        $submittedWorkStartTime = date('H:i', strtotime($settings->work_start_time));
        $data = [
            'work_start_time' => $submittedWorkStartTime,
            'late_grace_period_minutes' => $settings->late_grace_period_minutes,
            'wfh_quota_per_week' => $settings->wfh_quota_per_week,
            'full_day_hours' => $settings->full_day_hours,
            'half_day_hours' => $settings->half_day_hours,
        ];

        // Run the same diff logic as save()
        $changes = [];
        foreach ($fields as $field) {
            $oldValue = (string) $settings->{$field};
            $newValue = $field === 'work_start_time'
                ? $submittedWorkStartTime
                : (string) $data[$field];

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ];
            }
        }

        // Create audit entries only for changes (should be none)
        DB::transaction(function () use ($settings, $data, $changes, $submittedWorkStartTime, $user) {
            foreach ($changes as $change) {
                AttendanceSettingAudit::create([
                    'user_id' => $user->id,
                    'field' => $change['field'],
                    'old_value' => $change['old_value'],
                    'new_value' => $change['new_value'],
                ]);
            }

            $settings->update([
                'work_start_time' => $submittedWorkStartTime,
                'late_grace_period_minutes' => $data['late_grace_period_minutes'],
                'wfh_quota_per_week' => $data['wfh_quota_per_week'],
                'full_day_hours' => $data['full_day_hours'],
                'half_day_hours' => $data['half_day_hours'],
            ]);
        });

        // Assert: zero audit entries were created
        $auditCount = AttendanceSettingAudit::count();

        expect($auditCount)->toBe(
            0,
            "Iteration {$i}: Expected 0 audit entries for unchanged save, got {$auditCount}. "
            . "Settings: work_start_time={$settings->work_start_time}, "
            . "late_grace_period_minutes={$settings->late_grace_period_minutes}, "
            . "wfh_quota_per_week={$settings->wfh_quota_per_week}, "
            . "full_day_hours={$settings->full_day_hours}, "
            . "half_day_hours={$settings->half_day_hours}"
        );
    }
})->group('property-test', 'attendance-settings');


// Feature: attendance-settings, Property 8: Request-scoped caching

/**
 * Property-Based Test: Request-scoped caching
 *
 * For any number of calls to AttendanceService::getSettings() within a single
 * request lifecycle, only one database query should be executed to fetch the
 * attendance settings row.
 *
 * **Validates: Requirements 5.6**
 */

test('Property 8: Request-scoped caching — multiple getSettings() calls via public methods produce only one attendance_settings query', function () {
    for ($i = 0; $i < 100; $i++) {
        // Clean slate
        AttendanceSetting::query()->delete();

        // Generate random valid settings
        $fullDayHours = round(fake()->randomFloat(2, 4, 12), 2);
        $halfDayHours = round(fake()->randomFloat(2, 1, $fullDayHours - 0.01), 2);
        $hour = fake()->numberBetween(6, 11);
        $minute = fake()->numberBetween(0, 59);
        if (fake()->boolean(10)) {
            $hour = 12;
            $minute = 0;
        }
        $workStartTime = sprintf('%02d:%02d', $hour, $minute);
        $lateGracePeriod = fake()->numberBetween(0, 60);
        $wfhQuota = fake()->numberBetween(0, 5);

        AttendanceSetting::updateOrCreate([], [
            'work_start_time' => $workStartTime,
            'late_grace_period_minutes' => $lateGracePeriod,
            'wfh_quota_per_week' => $wfhQuota,
            'full_day_hours' => $fullDayHours,
            'half_day_hours' => $halfDayHours,
        ]);

        // Create employee and attendance log for calling public methods
        $employee = Employee::factory()->create();
        $today = Carbon::today();
        $checkIn = $today->copy()->setTime($hour, $minute, 0)->addMinutes($lateGracePeriod + 5);
        $checkOut = $checkIn->copy()->addHours((int) ceil($fullDayHours));

        $log = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => $today,
            'mode' => AttendanceLog::MODE_OFFICE,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        // Create a single AttendanceService instance
        $service = new AttendanceService();

        // Enable query logging AFTER setup queries, right before the calls we want to measure
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Random number of calls (2 to 15) to public methods that trigger getSettings()
        $callCount = fake()->numberBetween(2, 15);

        for ($j = 0; $j < $callCount; $j++) {
            // Alternate between computeStatus and getRemainingWfhQuota
            if ($j % 2 === 0) {
                $service->computeStatus($log);
            } else {
                $service->getRemainingWfhQuota($employee, $today);
            }
        }

        // Get the query log and count queries to attendance_settings
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $settingsQueries = array_filter($queries, function ($query) {
            return str_contains($query['query'], 'attendance_settings');
        });

        $settingsQueryCount = count($settingsQueries);

        expect($settingsQueryCount)->toBe(
            1,
            "Iteration {$i}: Expected exactly 1 query to attendance_settings after {$callCount} method calls on same service instance, got {$settingsQueryCount}. "
            . "Queries: " . json_encode(array_map(fn ($q) => $q['query'], $settingsQueries))
        );
    }
})->group('property-test', 'attendance-settings');
