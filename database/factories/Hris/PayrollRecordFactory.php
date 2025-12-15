<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollRecordFactory extends Factory
{
    protected $model = PayrollRecord::class;

    public function definition(): array
    {
        // Generate realistic Indonesian salary components
        $basicSalary = $this->faker->numberBetween(5000000, 30000000);
        $fixedAllowances = $this->faker->numberBetween(500000, 3000000);
        $variableAllowances = $this->faker->numberBetween(500000, 2000000);
        $grossIncome = $basicSalary + $fixedAllowances + $variableAllowances;

        // BPJS calculations (simplified)
        $bpjsKesehatanEmployee = min($basicSalary, 12000000) * 0.01;
        $bpjsKesehatanCompany = min($basicSalary, 12000000) * 0.04;
        $bpjsJhtEmployee = $basicSalary * 0.02;
        $bpjsJhtCompany = $basicSalary * 0.037;
        $bpjsJpEmployee = min($basicSalary, 10547400) * 0.01;
        $bpjsJpCompany = min($basicSalary, 10547400) * 0.02;
        $bpjsJkkCompany = $basicSalary * 0.0054;
        $bpjsJkmCompany = $basicSalary * 0.003;

        $totalBpjsEmployee = $bpjsKesehatanEmployee + $bpjsJhtEmployee + $bpjsJpEmployee;
        $taxableIncome = $grossIncome - $totalBpjsEmployee;

        // Simplified PPh 21 (5% for this range)
        $pph21 = $taxableIncome * 0.05;

        $otherDeductions = $this->faker->numberBetween(0, 500000);
        $totalDeductions = $totalBpjsEmployee + $pph21 + $otherDeductions;
        $netIncome = $grossIncome - $totalDeductions;

        return [
            'payrun_batch_id' => PayrunBatch::factory(),
            'employee_id' => Employee::factory(),
            'basic_salary' => $basicSalary,
            'total_fixed_allowances' => $fixedAllowances,
            'total_variable_allowances' => $variableAllowances,
            'gross_income' => $grossIncome,
            'taxable_income' => $taxableIncome,
            'bpjs_kesehatan_employee' => $bpjsKesehatanEmployee,
            'bpjs_kesehatan_company' => $bpjsKesehatanCompany,
            'bpjs_jht_employee' => $bpjsJhtEmployee,
            'bpjs_jht_company' => $bpjsJhtCompany,
            'bpjs_jp_employee' => $bpjsJpEmployee,
            'bpjs_jp_company' => $bpjsJpCompany,
            'bpjs_jkk_company' => $bpjsJkkCompany,
            'bpjs_jkm_company' => $bpjsJkmCompany,
            'pph21_amount' => $pph21,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'net_income' => $netIncome,
            'details' => [
                'allowances_fixed' => [
                    ['type' => 'position', 'name' => 'Tunjangan Jabatan', 'amount' => $fixedAllowances * 0.6],
                    ['type' => 'family', 'name' => 'Tunjangan Keluarga', 'amount' => $fixedAllowances * 0.4],
                ],
                'allowances_variable' => [
                    ['type' => 'transport', 'name' => 'Tunjangan Transport', 'amount' => $variableAllowances * 0.5],
                    ['type' => 'meal', 'name' => 'Uang Makan', 'amount' => $variableAllowances * 0.5],
                ],
            ],
        ];
    }

    /**
     * Set a specific payrun batch.
     */
    public function forBatch(PayrunBatch $batch): static
    {
        return $this->state(fn (array $attributes) => [
            'payrun_batch_id' => $batch->id,
        ]);
    }

    /**
     * Set a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Set specific salary amounts.
     */
    public function withSalary(float $basicSalary, float $fixedAllowances = 0, float $variableAllowances = 0): static
    {
        return $this->state(function (array $attributes) use ($basicSalary, $fixedAllowances, $variableAllowances) {
            $grossIncome = $basicSalary + $fixedAllowances + $variableAllowances;

            return [
                'basic_salary' => $basicSalary,
                'total_fixed_allowances' => $fixedAllowances,
                'total_variable_allowances' => $variableAllowances,
                'gross_income' => $grossIncome,
            ];
        });
    }
}
