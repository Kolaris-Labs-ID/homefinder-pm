<?php

use App\Filament\Resources\LeaveRequestResource\Pages\CreateLeaveRequest;
use App\Models\Hris\Employee;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        $this->admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']));
    }
});

// Covers the relationship('employee', ...) dropdowns (Training, PerformanceGoal, PerformanceReview, ClientVisit).
it('active() scope excludes inactive employees', function () {
    $active = Employee::factory()->create(['is_active' => true]);
    $inactive = Employee::factory()->create(['is_active' => false]);

    $ids = Employee::query()->active()->pluck('id');

    expect($ids)->toContain($active->id)->not->toContain($inactive->id);
});

// Covers the user-based dropdowns (LeaveRequest employee.user, OvertimeLog user).
it('whereHas active-employee closure excludes users of inactive employees', function () {
    $active = Employee::factory()->create(['is_active' => true]);
    $inactive = Employee::factory()->create(['is_active' => false]);

    $userIds = User::query()
        ->whereHas('employee', fn ($q) => $q->where('is_active', true))
        ->pluck('id');

    expect($userIds)->toContain($active->user_id)->not->toContain($inactive->user_id);
});

// End-to-end: the closure is actually wired into the rendered Filament form.
it('leave request create form omits inactive employees from the Employee dropdown', function () {
    $activeUser = User::factory()->create(['name' => 'Active Employee']);
    $inactiveUser = User::factory()->create(['name' => 'Inactive Employee']);
    Employee::factory()->create(['user_id' => $activeUser->id, 'is_active' => true]);
    Employee::factory()->create(['user_id' => $inactiveUser->id, 'is_active' => false]);

    Livewire::actingAs($this->admin)
        ->test(CreateLeaveRequest::class)
        ->assertFormFieldExists('employee_id', function ($field) {
            $labels = array_column($field->getOptionsForJs(), 'label');

            return in_array('Active Employee', $labels, true)
                && ! in_array('Inactive Employee', $labels, true);
        });
});
