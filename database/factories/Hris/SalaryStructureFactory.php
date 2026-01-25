<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    public function definition(): array
    {
        // Generate realistic Indonesian salary (IDR 5M - 50M)
        $basicSalary = $this->faker->numberBetween(5000000, 50000000);

        // Fixed allowances (must respect 75:25 rule)
        // Max fixed allowances = basic_salary / 0.75 - basic_salary = basic_salary * 0.333
        $maxFixedAllowances = $basicSalary * 0.333;

        $fixedAllowances = [
            ['type' => 'position', 'name' => 'Tunjangan Jabatan', 'amount' => min($this->faker->numberBetween(500000, 3000000), $maxFixedAllowances * 0.5)],
            ['type' => 'family', 'name' => 'Tunjangan Keluarga', 'amount' => min($this->faker->numberBetween(200000, 1000000), $maxFixedAllowances * 0.3)],
        ];

        $variableAllowances = [
            ['type' => 'transport', 'name' => 'Tunjangan Transport', 'amount' => $this->faker->numberBetween(300000, 1500000)],
            ['type' => 'meal', 'name' => 'Uang Makan', 'amount' => $this->faker->numberBetween(500000, 2000000)],
        ];

        $deductions = [
            ['type' => 'loan', 'name' => 'Potongan Pinjaman', 'amount' => $this->faker->boolean(30) ? $this->faker->numberBetween(100000, 500000) : 0],
        ];

        return [
            'employee_id' => Employee::factory(),
            'effective_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'basic_salary' => $basicSalary,
            'allowances_fixed' => $fixedAllowances,
            'allowances_variable' => $variableAllowances,
            'deductions' => $deductions,
            'pph21_method' => $this->faker->randomElement(SalaryStructure::getPph21Methods()),
            'jkk_risk_level' => $this->faker->randomElement(SalaryStructure::getJkkRiskLevels()),
            'prorate_enabled' => false, // Disable prorate by default in tests to avoid zero gross income
        ];
    }

    /**
     * Set a specific basic salary.
     */
    public function withBasicSalary(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'basic_salary' => $amount,
        ]);
    }

    /**
     * Set specific fixed allowances.
     */
    public function withFixedAllowances(array $allowances): static
    {
        return $this->state(fn (array $attributes) => [
            'allowances_fixed' => $allowances,
        ]);
    }

    /**
     * Set specific variable allowances.
     */
    public function withVariableAllowances(array $allowances): static
    {
        return $this->state(fn (array $attributes) => [
            'allowances_variable' => $allowances,
        ]);
    }

    /**
     * Set specific deductions.
     */
    public function withDeductions(array $deductions): static
    {
        return $this->state(fn (array $attributes) => [
            'deductions' => $deductions,
        ]);
    }

    /**
     * Set PPh 21 method to netto.
     */
    public function netto(): static
    {
        return $this->state(fn (array $attributes) => [
            'pph21_method' => SalaryStructure::PPH21_METHOD_NETTO,
        ]);
    }

    /**
     * Set PPh 21 method to gross.
     */
    public function gross(): static
    {
        return $this->state(fn (array $attributes) => [
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        ]);
    }

    /**
     * Set PPh 21 method to gross up.
     */
    public function grossUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS_UP,
        ]);
    }

    /**
     * Set a specific effective date.
     */
    public function effectiveFrom($date): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_date' => $date,
        ]);
    }

    /**
     * Create a structure that violates the 75:25 rule (for testing).
     */
    public function violating7525Rule(): static
    {
        return $this->state(function (array $attributes) {
            $basicSalary = 5000000;
            // Fixed allowances > 33.33% of basic = violates rule
            $fixedAllowances = [
                ['type' => 'position', 'name' => 'Tunjangan Jabatan', 'amount' => 3000000],
                ['type' => 'family', 'name' => 'Tunjangan Keluarga', 'amount' => 2000000],
            ];

            return [
                'basic_salary' => $basicSalary,
                'allowances_fixed' => $fixedAllowances,
            ];
        });
    }

    /**
     * Create a structure that complies with the 75:25 rule.
     */
    public function complying7525Rule(): static
    {
        return $this->state(function (array $attributes) {
            $basicSalary = 10000000;
            // Fixed allowances = 2M, total = 12M, basic/total = 83.3% > 75%
            $fixedAllowances = [
                ['type' => 'position', 'name' => 'Tunjangan Jabatan', 'amount' => 1500000],
                ['type' => 'family', 'name' => 'Tunjangan Keluarga', 'amount' => 500000],
            ];

            return [
                'basic_salary' => $basicSalary,
                'allowances_fixed' => $fixedAllowances,
            ];
        });
    }

    /**
     * Set JKK risk level.
     */
    public function withJkkRiskLevel(string $level): static
    {
        return $this->state(fn (array $attributes) => [
            'jkk_risk_level' => $level,
        ]);
    }
}
