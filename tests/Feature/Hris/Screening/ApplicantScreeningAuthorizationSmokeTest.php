<?php

use App\Models\Hris\Applicant;
use App\Models\User;
use Database\Seeders\RecruitmentScreeningPermissionSeeder;
use Spatie\Permission\Models\Permission;

it('denies screening view without the explicit permission, even with applicant access', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'view_applicant', 'guard_name' => 'web']));

    expect($user->can('viewScreening', Applicant::class))->toBeFalse();
});

it('allows screening view with the explicit recruitment.view_screening permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'recruitment.view_screening', 'guard_name' => 'web']));

    expect($user->can('viewScreening', Applicant::class))->toBeTrue();
});

it('lets hr-staff view and run screening but not override or clear', function () {
    $this->seed(RecruitmentScreeningPermissionSeeder::class);
    $staff = tap(User::factory()->create())->assignRole('hr-staff');

    expect($staff->can('viewScreening', Applicant::class))->toBeTrue()
        ->and($staff->can('runScreening', Applicant::class))->toBeTrue()
        ->and($staff->can('overrideScreening', Applicant::class))->toBeFalse()
        ->and($staff->can('clearScreeningOverride', Applicant::class))->toBeFalse();
});

it('lets hr-admin override and clear overrides', function () {
    $this->seed(RecruitmentScreeningPermissionSeeder::class);
    $admin = tap(User::factory()->create())->assignRole('hr-admin');

    expect($admin->can('overrideScreening', Applicant::class))->toBeTrue()
        ->and($admin->can('clearScreeningOverride', Applicant::class))->toBeTrue();
});
