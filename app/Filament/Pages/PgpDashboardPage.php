<?php

namespace App\Filament\Pages;

use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Services\Hris\PgpService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class PgpDashboardPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Homefinder Growth Point';
    protected static ?string $title = 'Homefinder Growth Point Dashboard';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.pgp-dashboard';
    protected static ?string $navigationGroup = 'HRIS';
    protected static ?string $slug = 'pgp-dashboard';

    protected PgpService $pgpService;

    public function boot(PgpService $pgpService): void
    {
        $this->pgpService = $pgpService;
    }

    public function getSubheading(): ?string
    {
        return 'Track your training achievements and rewards';
    }

    /**
     * Get the current employee for the logged-in user.
     */
    protected function getCurrentEmployee(): ?Employee
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)->first();
    }

    /**
     * Get current quarter PGP summary for the logged-in employee.
     *
     * @return array{
     *   currentPoints: int,
     *   targetPoints: int,
     *   progressPercentage: float,
     *   projectedReward: int,
     *   quarter: int,
     *   year: int
     * }
     */
    public function getPgpSummary(): array
    {
        $employee = $this->getCurrentEmployee();
        $quarter = $this->pgpService->getCurrentQuarter();

        if (!$employee) {
            return [
                'currentPoints' => 0,
                'targetPoints' => PgpService::PGP_TARGET,
                'progressPercentage' => 0.0,
                'projectedReward' => 0,
                'quarter' => $quarter['quarter'],
                'year' => $quarter['year'],
            ];
        }

        $currentPoints = $this->pgpService->getQuarterlyPoints($employee);
        $progressPercentage = $this->pgpService->getProgressPercentage($currentPoints);
        $projectedReward = $this->pgpService->calculateReward($currentPoints);

        return [
            'currentPoints' => $currentPoints,
            'targetPoints' => PgpService::PGP_TARGET,
            'progressPercentage' => $progressPercentage,
            'projectedReward' => $projectedReward,
            'quarter' => $quarter['quarter'],
            'year' => $quarter['year'],
        ];
    }

    /**
     * Get list of approved training records with PGP for current quarter.
     *
     * @return Collection<TrainingRecord>
     */
    public function getApprovedTrainings(): Collection
    {
        $employee = $this->getCurrentEmployee();

        if (!$employee) {
            return collect();
        }

        return $this->pgpService->getQuarterlyTrainingRecords($employee);
    }

    /**
     * Format amount as Indonesian Rupiah.
     */
    public function formatRupiah(int $amount): string
    {
        return 'Rp' . number_format($amount, 0, ',', '.');
    }

    /**
     * Get quarter label (e.g., "Q1 2026").
     */
    public function getQuarterLabel(): string
    {
        $summary = $this->getPgpSummary();
        return "Q{$summary['quarter']} {$summary['year']}";
    }
}
