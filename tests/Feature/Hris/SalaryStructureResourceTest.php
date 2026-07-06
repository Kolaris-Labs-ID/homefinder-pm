<?php

use App\Filament\Resources\SalaryStructureResource;
use App\Filament\Resources\SalaryStructureResource\Pages\CreateSalaryStructure;
use App\Filament\Resources\SalaryStructureResource\Pages\EditSalaryStructure;
use App\Filament\Resources\SalaryStructureResource\Pages\ListSalaryStructures;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
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

describe('SalaryStructureResource List Page', function () {
    it('can render list page', function () {
        $this->actingAs($this->admin)
            ->get(SalaryStructureResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('can list salary structures', function () {
        $employee = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListSalaryStructures::class)
            ->assertCanSeeTableRecords(SalaryStructure::all());
    });
});

describe('SalaryStructureResource Create Page with New Fields', function () {
    it('can render create page', function () {
        $this->actingAs($this->admin)
            ->get(SalaryStructureResource::getUrl('create'))
            ->assertSuccessful();
    });

    it('can create salary structure with new fields', function () {
        $employee = Employee::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(CreateSalaryStructure::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'effective_date' => now()->format('Y-m-d'),
                'basic_salary' => 4000000,
                'pph21_method' => 'gross',
                'jkk_risk_level' => 'low',
                'daily_allowance_rate' => 70000,
                'prorate_enabled' => true,
                'bpjs_paid_by' => 'company',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $structure = SalaryStructure::where('employee_id', $employee->id)->first();
        expect($structure)->not->toBeNull()
            ->and((float) $structure->daily_allowance_rate)->toBe(70000.00)
            ->and($structure->prorate_enabled)->toBeTrue()
            ->and($structure->bpjs_paid_by)->toBe('company');
    });

    it('can create salary structure with BPJS paid by employee (default)', function () {
        $employee = Employee::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(CreateSalaryStructure::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'effective_date' => now()->format('Y-m-d'),
                'basic_salary' => 5000000,
                'pph21_method' => 'gross',
                'jkk_risk_level' => 'low',
                'bpjs_paid_by' => 'employee',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $structure = SalaryStructure::where('employee_id', $employee->id)->first();
        expect($structure->bpjs_paid_by)->toBe('employee');
    });

    it('rejects a duplicate effective_date for the same employee', function () {
        $employee = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-06-01',
            'basic_salary' => 5000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CreateSalaryStructure::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'effective_date' => '2025-06-01',
                'basic_salary' => 6000000,
                'pph21_method' => 'gross',
                'jkk_risk_level' => 'low',
            ])
            ->call('create')
            ->assertHasFormErrors(['effective_date']);

        expect(SalaryStructure::where('employee_id', $employee->id)->count())->toBe(1);
    });

    it('allows the same effective_date for a different employee', function () {
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employeeA->id,
            'effective_date' => '2025-06-01',
            'basic_salary' => 5000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CreateSalaryStructure::class)
            ->fillForm([
                'employee_id' => $employeeB->id,
                'effective_date' => '2025-06-01',
                'basic_salary' => 6000000,
                'pph21_method' => 'gross',
                'jkk_risk_level' => 'low',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    });
});

describe('SalaryStructureResource Edit Page with New Fields', function () {
    it('can render edit page', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $this->actingAs($this->admin)
            ->get(SalaryStructureResource::getUrl('edit', ['record' => $structure]))
            ->assertSuccessful();
    });

    it('can update salary structure with new fields', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
            'daily_allowance_rate' => null,
            'prorate_enabled' => true,
            'bpjs_paid_by' => 'employee',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditSalaryStructure::class, ['record' => $structure->id])
            ->fillForm([
                'daily_allowance_rate' => 70000,
                'prorate_enabled' => false,
                'bpjs_paid_by' => 'company',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $structure->refresh();
        expect((float) $structure->daily_allowance_rate)->toBe(70000.00)
            ->and($structure->prorate_enabled)->toBeFalse()
            ->and($structure->bpjs_paid_by)->toBe('company');
    });

    it('can update variable allowances with attendance-based type', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        Livewire::actingAs($this->admin)
            ->test(EditSalaryStructure::class, ['record' => $structure->id])
            ->fillForm([
                'allowances_variable' => [
                    [
                        'name' => 'Uang Makan',
                        'type' => 'attendance_based',
                        'per_day_amount' => 50000,
                        'count_late' => true,
                        'count_wfh' => true,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $structure->refresh();
        expect($structure->allowances_variable)->not->toBeEmpty()
            ->and($structure->allowances_variable[0]['name'])->toBe('Uang Makan')
            ->and($structure->allowances_variable[0]['type'])->toBe('attendance_based');
    });
});

describe('SalaryStructureResource Table Columns', function () {
    it('displays new fields in table', function () {
        $employee = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
            'daily_allowance_rate' => 70000,
            'prorate_enabled' => true,
            'bpjs_paid_by' => 'company',
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListSalaryStructures::class)
            ->assertCanSeeTableRecords(SalaryStructure::all());
    });
});
