<?php

/**
 * Unit Tests: Attendance Setting
 *
 * Tests for default fallback behavior when the attendance_settings table is empty.
 *
 * _Requirements: 1.3, 5.5_
 */

use App\Models\Hris\AttendanceSetting;

test('getSettings returns default values when attendance_settings table is empty', function () {
    // Truncate the table to ensure it's empty
    AttendanceSetting::query()->truncate();

    // Call getSettings — should create a row with defaults via firstOrCreate
    $settings = AttendanceSetting::getSettings();

    // Assert each returned value matches the DEFAULTS constant
    expect($settings->work_start_time)->toBe(AttendanceSetting::DEFAULTS['work_start_time']);
    expect($settings->late_grace_period_minutes)->toBe(AttendanceSetting::DEFAULTS['late_grace_period_minutes']);
    expect($settings->wfh_quota_per_week)->toBe(AttendanceSetting::DEFAULTS['wfh_quota_per_week']);
    expect((float) $settings->full_day_hours)->toEqual((float) AttendanceSetting::DEFAULTS['full_day_hours']);
    expect((float) $settings->half_day_hours)->toEqual((float) AttendanceSetting::DEFAULTS['half_day_hours']);
})->group('unit-test', 'attendance-settings');

/**
 * Unit Tests: Authorization denies unauthorized users
 *
 * _Requirements: 2.3_
 */

use App\Filament\Pages\AttendanceSettings;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('canAccess returns false for user without view_attendance_setting permission', function () {
    // Create the permission so it exists in the DB
    Permission::firstOrCreate(['name' => 'view_attendance_setting', 'guard_name' => 'web']);

    // Create a user without the permission
    $user = User::factory()->create();

    // Authenticate as this user
    $this->actingAs($user);

    expect(AttendanceSettings::canAccess())->toBeFalse();
})->group('unit-test', 'attendance-settings');

test('canAccess returns true for user with view_attendance_setting permission', function () {
    // Create the permission
    $permission = Permission::firstOrCreate(['name' => 'view_attendance_setting', 'guard_name' => 'web']);

    // Create a user and assign the permission via a role
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    // Authenticate as this user
    $this->actingAs($user);

    expect(AttendanceSettings::canAccess())->toBeTrue();
})->group('unit-test', 'attendance-settings');

test('canAccess returns false when no user is authenticated', function () {
    // Ensure no user is logged in
    auth()->logout();

    expect(AttendanceSettings::canAccess())->toBeFalse();
})->group('unit-test', 'attendance-settings');

/**
 * Unit Test: Cross-field validation edge case
 *
 * Setting half_day_hours equal to full_day_hours should trigger a validation error.
 *
 * _Requirements: 3.7_
 */

use Livewire\Livewire;

test('validation rejects half_day_hours equal to full_day_hours', function () {
    // Ensure a settings row exists
    AttendanceSetting::getSettings();

    // Create an authorized user
    $permission = Permission::firstOrCreate(['name' => 'view_attendance_setting', 'guard_name' => 'web']);
    $updatePermission = Permission::firstOrCreate(['name' => 'update_attendance_setting', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'test-admin', 'guard_name' => 'web']);
    $role->givePermissionTo([$permission, $updatePermission]);
    $user->assignRole($role);

    $this->actingAs($user);

    Livewire::test(AttendanceSettings::class)
        ->fillForm([
            'work_start_time' => '09:00',
            'late_grace_period_minutes' => 15,
            'wfh_quota_per_week' => 1,
            'full_day_hours' => 8,
            'half_day_hours' => 8, // equal to full_day_hours — should be rejected
        ])
        ->call('save')
        ->assertHasFormErrors(['half_day_hours']);
})->group('unit-test', 'attendance-settings');

/**
 * Unit Test: Audit history display
 *
 * After saving changes to settings, getAuditLogs() should return entries
 * with correct user, field, old_value, and new_value — with user relationship eager-loaded.
 *
 * _Requirements: 4.3_
 */

use App\Models\Hris\AttendanceSettingAudit;

test('getAuditLogs returns entries with correct user, field, old and new values after saving changes', function () {
    // Clear any existing audit entries
    AttendanceSettingAudit::query()->truncate();

    // Ensure a settings row exists with known initial values
    $settings = AttendanceSetting::getSettings();
    $settings->update([
        'work_start_time' => '09:00',
        'late_grace_period_minutes' => 15,
        'wfh_quota_per_week' => 1,
        'full_day_hours' => 8.00,
        'half_day_hours' => 4.00,
    ]);

    // Create an authorized user with both permissions
    $viewPerm = Permission::firstOrCreate(['name' => 'view_attendance_setting', 'guard_name' => 'web']);
    $updatePerm = Permission::firstOrCreate(['name' => 'update_attendance_setting', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'audit-test-admin', 'guard_name' => 'web']);
    $role->givePermissionTo([$viewPerm, $updatePerm]);
    $user->assignRole($role);

    $this->actingAs($user);

    // Save changed values via Livewire (change 2 fields: late_grace_period_minutes and wfh_quota_per_week)
    // Use string values matching the decimal:2 cast format to avoid false diffs on full_day_hours/half_day_hours
    Livewire::test(AttendanceSettings::class)
        ->fillForm([
            'work_start_time' => '09:00',
            'late_grace_period_minutes' => 30,
            'wfh_quota_per_week' => 3,
            'full_day_hours' => '8.00',
            'half_day_hours' => '4.00',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Instantiate the page and call getAuditLogs()
    $page = new AttendanceSettings();
    $logs = $page->getAuditLogs();

    // Assert exactly 2 audit entries were created (one per changed field)
    expect($logs)->toHaveCount(2);

    // Collect entries by field name for easier assertion
    $logsByField = $logs->keyBy('field');

    // Assert late_grace_period_minutes audit entry
    expect($logsByField->has('late_grace_period_minutes'))->toBeTrue();
    $graceLog = $logsByField->get('late_grace_period_minutes');
    expect($graceLog->user_id)->toBe($user->id);
    expect($graceLog->old_value)->toBe('15');
    expect($graceLog->new_value)->toBe('30');

    // Assert wfh_quota_per_week audit entry
    expect($logsByField->has('wfh_quota_per_week'))->toBeTrue();
    $wfhLog = $logsByField->get('wfh_quota_per_week');
    expect($wfhLog->user_id)->toBe($user->id);
    expect($wfhLog->old_value)->toBe('1');
    expect($wfhLog->new_value)->toBe('3');

    // Assert the user relationship is loaded (eager-loaded via with('user'))
    $logs->each(function ($log) use ($user) {
        expect($log->relationLoaded('user'))->toBeTrue();
        expect($log->user)->not->toBeNull();
        expect($log->user->id)->toBe($user->id);
    });
})->group('unit-test', 'attendance-settings');
