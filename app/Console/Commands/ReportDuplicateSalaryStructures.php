<?php

namespace App\Console\Commands;

use App\Models\Hris\Employee;
use App\Services\Hris\DuplicateSalaryStructureFinder;
use Illuminate\Console\Command;

class ReportDuplicateSalaryStructures extends Command
{
    protected $signature = 'hris:salary-structures:report-duplicates';

    protected $description = 'Read-only report of employees with more than one salary structure on the same effective_date — resolve these manually before the unique-constraint migration runs';

    public function handle(DuplicateSalaryStructureFinder $finder): int
    {
        $duplicates = $finder->find();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate salary structures found. Safe to run the unique-constraint migration.');

            return self::SUCCESS;
        }

        $names = Employee::whereIn('id', $duplicates->pluck('employee_id'))
            ->with('user')
            ->get()
            ->mapWithKeys(fn (Employee $employee) => [$employee->id => $employee->user?->name ?? "Employee #{$employee->id}"]);

        $this->warn("{$duplicates->count()} employee/effective_date combination(s) have duplicate salary structures:");

        foreach ($duplicates as $d) {
            $name = $names[$d['employee_id']] ?? "Employee #{$d['employee_id']}";
            $this->line("- Employee: {$name}");
            $this->line("  Effective date: {$d['effective_date']}");
            $this->line('  Structure IDs: '.implode(', ', $d['structure_ids']));
            $this->line('  Basic salaries: '.implode(' / ', $d['basic_salaries']));
            $this->line('  Daily allowance rates: '.implode(' / ', $d['daily_allowance_rates']));
        }

        $this->table(
            ['Employee', 'Effective Date', 'Count', 'Structure IDs', 'Basic Salaries', 'Daily Allowance Rates', 'Suggested Action'],
            $duplicates->map(fn (array $d) => [
                $names[$d['employee_id']] ?? "Employee #{$d['employee_id']}",
                $d['effective_date'],
                $d['duplicate_count'],
                implode(', ', $d['structure_ids']),
                implode(' / ', $d['basic_salaries']),
                implode(' / ', $d['daily_allowance_rates']),
                'Manual review required — keep the correct row, delete/merge the rest.',
            ])
        );

        return self::FAILURE;
    }
}
