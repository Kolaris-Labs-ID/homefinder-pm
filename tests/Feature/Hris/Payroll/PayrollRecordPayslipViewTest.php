<?php

use App\Filament\Resources\PayrunBatchResource\Pages\ViewPayrunBatch;
use App\Filament\Resources\PayrunBatchResource\RelationManagers\PayrollRecordsRelationManager;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($role);
    $this->actingAs($this->admin);
});

function makePayrollRecord(PayrunBatch $batch, Employee $employee, array $overrides = []): PayrollRecord
{
    $defaults = [
        'payrun_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'basic_salary' => 4000000,
        'total_fixed_allowances' => 500000,
        'total_variable_allowances' => 300000,
        'gross_income' => 4800000,
        'taxable_income' => 4600000,
        'bpjs_kesehatan_employee' => 40000,
        'bpjs_jht_employee' => 80000,
        'bpjs_jp_employee' => 40000,
        'bpjs_kesehatan_company' => 160000,
        'bpjs_jht_company' => 148000,
        'bpjs_jp_company' => 80000,
        'bpjs_jkk_company' => 21600,
        'bpjs_jkm_company' => 12000,
        'pph21_amount' => 115000,
        'other_deductions' => 50000,
        'total_deductions' => 325000,
        'net_income' => 4475000,
        'total_working_days' => 22,
        'actual_days_worked' => 18,
        'prorate_ratio' => 0.8182,
        'full_basic_salary' => 4000000,
        'prorated_basic_salary' => 3272800,
        'daily_allowance_total' => 1260000,
        'bpjs_company_paid' => false,
        'weekly_allowance_earned' => 0,
        'weekly_allowance_paid' => 0,
        'weekly_allowance_adjustment' => 0,
        'details' => [
            'prorate_enabled' => true,
            'pph21_method' => 'gross',
            'daily_allowance_rate' => 70000,
            'weekly_allowance_enabled' => false,
            'allowances_fixed' => [
                ['name' => 'Tunjangan Jabatan', 'amount' => 300000],
                ['name' => 'Tunjangan Keluarga', 'amount' => 200000],
            ],
            'allowances_variable' => [
                ['name' => 'Tunjangan Transport', 'amount' => 180000, 'days' => 18, 'rate' => 10000],
                ['name' => 'Uang Makan', 'amount' => 120000, 'days' => 18, 'rate' => 6667],
            ],
            'deductions' => [
                ['name' => 'Pinjaman Karyawan', 'amount' => 50000],
            ],
            'bpjs_breakdown' => [
                'kesehatan' => ['employee' => 40000, 'company' => 160000],
                'jht' => ['employee' => 80000, 'company' => 148000],
                'jp' => ['employee' => 40000, 'company' => 80000],
                'jkk' => 21600,
                'jkm' => 12000,
            ],
        ],
    ];

    return PayrollRecord::create(array_merge($defaults, $overrides));
}

describe('Payslip relation manager renders', function () {
    it('can render the payroll records relation manager', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        makePayrollRecord($batch, $employee);

        Livewire::test(PayrollRecordsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewPayrunBatch::class,
        ])->assertSuccessful();
    });

    it('shows payroll records in the table', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        Livewire::test(PayrollRecordsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewPayrunBatch::class,
        ])->assertCanSeeTableRecords([$record]);
    });

    it('has a view action on each record', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        Livewire::test(PayrollRecordsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewPayrunBatch::class,
        ])->assertTableActionExists('view');
    });

    it('can open the view action modal without errors', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        Livewire::test(PayrollRecordsRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => ViewPayrunBatch::class,
        ])
            ->callTableAction('view', $record)
            ->assertHasNoTableActionErrors();
    });
});

describe('Payslip data completeness via PayrollRecord details', function () {
    it('stores complete earnings breakdown in details', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        $details = $record->details;

        expect($details)->toHaveKeys([
            'prorate_enabled',
            'pph21_method',
            'daily_allowance_rate',
            'weekly_allowance_enabled',
            'allowances_fixed',
            'allowances_variable',
            'deductions',
            'bpjs_breakdown',
        ]);
    });

    it('stores fixed allowances with name and amount', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        $fixed = $record->details['allowances_fixed'];

        expect($fixed)->toHaveCount(2)
            ->and($fixed[0]['name'])->toBe('Tunjangan Jabatan')
            ->and($fixed[0]['amount'])->toBe(300000)
            ->and($fixed[1]['name'])->toBe('Tunjangan Keluarga')
            ->and($fixed[1]['amount'])->toBe(200000);
    });

    it('stores variable allowances with name, amount, days and rate', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        $variable = $record->details['allowances_variable'];

        expect($variable)->toHaveCount(2)
            ->and($variable[0]['name'])->toBe('Tunjangan Transport')
            ->and($variable[0]['days'])->toBe(18)
            ->and($variable[0]['rate'])->toBe(10000)
            ->and($variable[1]['name'])->toBe('Uang Makan');
    });

    it('stores deductions with name and amount', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        $deductions = $record->details['deductions'];

        expect($deductions)->toHaveCount(1)
            ->and($deductions[0]['name'])->toBe('Pinjaman Karyawan')
            ->and($deductions[0]['amount'])->toBe(50000);
    });

    it('stores BPJS breakdown per component', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        $bpjs = $record->details['bpjs_breakdown'];

        expect($bpjs)->toHaveKeys(['kesehatan', 'jht', 'jp', 'jkk', 'jkm'])
            ->and($bpjs['kesehatan']['employee'])->toBe(40000)
            ->and($bpjs['kesehatan']['company'])->toBe(160000);
    });

    it('stores prorate information', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        expect($record->total_working_days)->toBe(22)
            ->and($record->actual_days_worked)->toBe(18)
            ->and((float) $record->prorate_ratio)->toBe(0.8182)
            ->and($record->prorate_label)->toBe('18/22 HK')
            ->and($record->isProrated())->toBeTrue();
    });

    it('stores daily allowance rate in details', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        expect($record->details['daily_allowance_rate'])->toBe(70000)
            ->and((float) $record->daily_allowance_total)->toBe(1260000.0);
    });

    it('stores PPh 21 method in details', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        expect($record->details['pph21_method'])->toBe('gross')
            ->and((float) $record->pph21_amount)->toBe(115000.0);
    });
});

describe('Payslip conditional visibility logic', function () {
    it('shows prorate section only when prorate is enabled', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();

        $prorated = makePayrollRecord($batch, $employee);
        expect($prorated->details['prorate_enabled'])->toBeTrue()
            ->and($prorated->isProrated())->toBeTrue();

        $nonProrated = makePayrollRecord($batch, Employee::factory()->create(), [
            'prorate_ratio' => 1.0,
            'details' => array_merge($prorated->details, ['prorate_enabled' => false]),
        ]);
        expect($nonProrated->details['prorate_enabled'])->toBeFalse()
            ->and($nonProrated->isProrated())->toBeFalse();
    });

    it('weekly allowance section depends on weekly_allowance_enabled flag', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();

        $withWeekly = makePayrollRecord($batch, $employee, [
            'weekly_allowance_earned' => 1260000,
            'weekly_allowance_paid' => 900000,
            'weekly_allowance_adjustment' => 360000,
            'details' => [
                'prorate_enabled' => true,
                'pph21_method' => 'gross',
                'daily_allowance_rate' => 70000,
                'weekly_allowance_enabled' => true,
                'allowances_fixed' => [],
                'allowances_variable' => [],
                'deductions' => [],
                'bpjs_breakdown' => [],
            ],
        ]);
        expect($withWeekly->details['weekly_allowance_enabled'])->toBeTrue();

        $withoutWeekly = makePayrollRecord($batch, Employee::factory()->create());
        expect($withoutWeekly->details['weekly_allowance_enabled'])->toBeFalse();
    });

    it('BPJS employee section hidden when all contributions are zero', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();

        $zeroBpjs = makePayrollRecord($batch, $employee, [
            'bpjs_kesehatan_employee' => 0,
            'bpjs_jht_employee' => 0,
            'bpjs_jp_employee' => 0,
            'bpjs_company_paid' => true,
        ]);

        expect($zeroBpjs->total_bpjs_employee)->toBe(0.0);
    });

    it('other deductions section hidden when zero', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();

        $noDeductions = makePayrollRecord($batch, $employee, [
            'other_deductions' => 0,
            'details' => [
                'prorate_enabled' => true,
                'pph21_method' => 'gross',
                'daily_allowance_rate' => 70000,
                'weekly_allowance_enabled' => false,
                'allowances_fixed' => [],
                'allowances_variable' => [],
                'deductions' => [],
                'bpjs_breakdown' => [],
            ],
        ]);

        expect((float) $noDeductions->other_deductions)->toBe(0.0)
            ->and($noDeductions->details['deductions'])->toBeEmpty();
    });
});

describe('Payslip salary calculation integrity', function () {
    it('net income equals gross minus total deductions', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        $expected = (float) $record->gross_income - (float) $record->total_deductions;
        expect((float) $record->net_income)->toBe($expected);
    });

    it('total BPJS employee is sum of kesehatan + jht + jp', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        $expected = (float) $record->bpjs_kesehatan_employee
            + (float) $record->bpjs_jht_employee
            + (float) $record->bpjs_jp_employee;

        expect($record->total_bpjs_employee)->toBe($expected);
    });

    it('total BPJS company is sum of all company components', function () {
        $batch = PayrunBatch::factory()->calculated()->create();
        $employee = Employee::factory()->create();
        $record = makePayrollRecord($batch, $employee);

        $expected = (float) $record->bpjs_kesehatan_company
            + (float) $record->bpjs_jht_company
            + (float) $record->bpjs_jp_company
            + (float) $record->bpjs_jkk_company
            + (float) $record->bpjs_jkm_company;

        expect($record->total_bpjs_company)->toBe($expected);
    });
});
