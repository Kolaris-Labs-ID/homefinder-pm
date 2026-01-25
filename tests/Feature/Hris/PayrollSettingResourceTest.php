<?php

use App\Filament\Resources\PayrollSettingResource;
use App\Filament\Resources\PayrollSettingResource\Pages\CreatePayrollSetting;
use App\Filament\Resources\PayrollSettingResource\Pages\EditPayrollSetting;
use App\Filament\Resources\PayrollSettingResource\Pages\ListPayrollSettings;
use App\Models\Hris\PayrollSetting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    // Assign super_admin role if using Spatie Permission
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
        $this->admin->assignRole($role);
    }
});

describe('PayrollSettingResource List Page', function () {
    it('can render list page', function () {
        $this->actingAs($this->admin)
            ->get(PayrollSettingResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('can list payroll settings', function () {
        PayrollSetting::create([
            'key' => 'test_setting',
            'value' => ['rate' => 0.01],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'Test Setting',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListPayrollSettings::class)
            ->assertCanSeeTableRecords(PayrollSetting::all());
    });

    it('can filter by category', function () {
        PayrollSetting::create([
            'key' => 'bpjs_setting',
            'value' => [],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Setting',
        ]);
        PayrollSetting::create([
            'key' => 'tax_setting',
            'value' => [],
            'category' => PayrollSetting::CATEGORY_TAX,
            'label' => 'Tax Setting',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListPayrollSettings::class)
            ->filterTable('category', PayrollSetting::CATEGORY_BPJS)
            ->assertCanSeeTableRecords(
                PayrollSetting::where('category', PayrollSetting::CATEGORY_BPJS)->get()
            )
            ->assertCanNotSeeTableRecords(
                PayrollSetting::where('category', PayrollSetting::CATEGORY_TAX)->get()
            );
    });
});

describe('PayrollSettingResource Create Page', function () {
    it('can render create page', function () {
        $this->actingAs($this->admin)
            ->get(PayrollSettingResource::getUrl('create'))
            ->assertSuccessful();
    });

    it('can create payroll setting', function () {
        Livewire::actingAs($this->admin)
            ->test(CreatePayrollSetting::class)
            ->fillForm([
                'key' => 'new_test_setting',
                'value' => ['rate' => 0.05],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'New Test Setting',
                'description' => 'A test setting',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(PayrollSetting::where('key', 'new_test_setting')->exists())->toBeTrue();

        $setting = PayrollSetting::where('key', 'new_test_setting')->first();
        expect($setting->value)->toBe(['rate' => '0.05'])
            ->and($setting->category)->toBe('bpjs')
            ->and($setting->label)->toBe('New Test Setting');
    });

    it('validates unique key', function () {
        PayrollSetting::create([
            'key' => 'existing_key',
            'value' => [],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Existing',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CreatePayrollSetting::class)
            ->fillForm([
                'key' => 'existing_key',
                'value' => [],
                'category' => PayrollSetting::CATEGORY_GENERAL,
                'label' => 'Duplicate',
            ])
            ->call('create')
            ->assertHasFormErrors(['key' => 'unique']);
    });

    it('validates key format', function () {
        Livewire::actingAs($this->admin)
            ->test(CreatePayrollSetting::class)
            ->fillForm([
                'key' => 'Invalid Key With Spaces',
                'value' => [],
                'category' => PayrollSetting::CATEGORY_GENERAL,
                'label' => 'Test',
            ])
            ->call('create')
            ->assertHasFormErrors(['key']);
    });
});

describe('PayrollSettingResource Edit Page', function () {
    it('can render edit page', function () {
        $setting = PayrollSetting::create([
            'key' => 'edit_test',
            'value' => ['rate' => 0.01],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'Edit Test',
        ]);

        $this->actingAs($this->admin)
            ->get(PayrollSettingResource::getUrl('edit', ['record' => $setting]))
            ->assertSuccessful();
    });

    it('can update payroll setting', function () {
        $setting = PayrollSetting::create([
            'key' => 'update_test',
            'value' => ['rate' => 0.01],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'Update Test',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditPayrollSetting::class, ['record' => $setting->id])
            ->fillForm([
                'value' => ['rate' => 0.02],
                'label' => 'Updated Label',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting->refresh();
        expect($setting->value)->toBe(['rate' => '0.02'])
            ->and($setting->label)->toBe('Updated Label');
    });

    it('can delete payroll setting', function () {
        $setting = PayrollSetting::create([
            'key' => 'delete_test',
            'value' => [],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Delete Test',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditPayrollSetting::class, ['record' => $setting->id])
            ->callAction('delete');

        expect(PayrollSetting::find($setting->id))->toBeNull();
    });
});
