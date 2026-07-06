<?php

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\CompensationResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStructure(Employee $employee, string $effectiveDate, float $dailyRate): SalaryStructure
{
    return SalaryStructure::create([
        'employee_id' => $employee->id,
        'effective_date' => $effectiveDate,
        'basic_salary' => 4_000_000,
        'daily_allowance_rate' => $dailyRate,
        'pph21_method' => 'gross',
        'jkk_risk_level' => 'low',
    ]);
}

beforeEach(function () {
    $this->resolver = new CompensationResolver;
    $this->employee = Employee::factory()->create();
});

describe('CompensationResolver::resolve (single point-in-time)', function () {
    it('returns the newest structure effective on or before the work date', function () {
        makeStructure($this->employee, '2025-01-01', 50_000);
        $mar = makeStructure($this->employee, '2025-03-01', 100_000);

        $resolved = $this->resolver->resolve($this->employee, Carbon::parse('2025-06-15'));

        expect($resolved->id)->toBe($mar->id);
    });

    it('returns null when every structure is effective after the work date', function () {
        makeStructure($this->employee, '2025-03-01', 100_000);

        $resolved = $this->resolver->resolve($this->employee, Carbon::parse('2025-01-01'));

        expect($resolved)->toBeNull();
    });

    it('includes a structure whose effective_date equals the work date (boundary inclusive)', function () {
        $exact = makeStructure($this->employee, '2025-04-10', 70_000);

        $resolved = $this->resolver->resolve($this->employee, Carbon::parse('2025-04-10'));

        expect($resolved->id)->toBe($exact->id);
    });
});

describe('CompensationResolver per-day resolution over a preloaded set', function () {
    it('picks the old rate before a mid-week change and the new rate on/after', function () {
        makeStructure($this->employee, '2025-01-01', 50_000);
        makeStructure($this->employee, '2025-10-08', 100_000); // effective Wednesday

        $structures = $this->resolver->structuresAsOf($this->employee, Carbon::parse('2025-10-10'));

        expect($this->resolver->dailyRateFrom($structures, Carbon::parse('2025-10-07')))->toBe(50_000.0) // Tue
            ->and($this->resolver->dailyRateFrom($structures, Carbon::parse('2025-10-08')))->toBe(100_000.0) // Wed (boundary)
            ->and($this->resolver->dailyRateFrom($structures, Carbon::parse('2025-10-10')))->toBe(100_000.0); // Fri
    });

    it('returns 0.0 rate for a day before any structure is effective', function () {
        makeStructure($this->employee, '2025-10-08', 100_000);

        $structures = $this->resolver->structuresAsOf($this->employee, Carbon::parse('2025-10-10'));

        expect($this->resolver->dailyRateFrom($structures, Carbon::parse('2025-10-06')))->toBe(0.0);
    });

    it('loads structures once and resolves without further queries', function () {
        makeStructure($this->employee, '2025-01-01', 50_000);
        makeStructure($this->employee, '2025-10-08', 100_000);

        $structures = $this->resolver->structuresAsOf($this->employee, Carbon::parse('2025-10-10'));

        DB::enableQueryLog();
        $this->resolver->resolveFrom($structures, Carbon::parse('2025-10-07'));
        $this->resolver->resolveFrom($structures, Carbon::parse('2025-10-09'));
        expect(DB::getQueryLog())->toBeEmpty();
        DB::disableQueryLog();
    });
});
