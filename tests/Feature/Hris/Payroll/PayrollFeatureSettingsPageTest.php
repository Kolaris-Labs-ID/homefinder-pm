<?php

use App\Filament\Pages\PayrollFeatureSettings;
use App\Models\Hris\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
    $this->admin->assignRole($role);
});

it('toggles the segmentation flag and persists it to payroll_settings', function () {
    Livewire::actingAs($this->admin)
        ->test(PayrollFeatureSettings::class)
        ->fillForm(['segmentation_enabled' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(PayrollSetting::getValueField('salary_effective_date_segmentation', 'enabled', null))->toBeTrue();

    Livewire::actingAs($this->admin)
        ->test(PayrollFeatureSettings::class)
        ->fillForm(['segmentation_enabled' => false])
        ->call('save');

    expect(PayrollSetting::getValueField('salary_effective_date_segmentation', 'enabled', null))->toBeFalse();
});

it('is gated to super_admin', function () {
    expect(PayrollFeatureSettings::canAccess())->toBeFalse(); // no auth
});
