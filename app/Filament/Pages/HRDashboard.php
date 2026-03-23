<?php

namespace App\Filament\Pages;

use App\Exports\HeadcountExport;
use App\Exports\RecruitmentReportExport;
use App\Exports\TurnoverExport;
use App\Services\Hris\HRAnalyticsService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

class HRDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'HR Dashboard';
    protected static ?string $title = 'HR Analytics Dashboard';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.hr-dashboard';
    protected static ?string $navigationGroup = 'HR Analytics';
    protected static ?string $slug = 'hr-dashboard';

    public ?string $startDate = null;
    public ?string $endDate = null;
    public int $leaveYear;

    protected HRAnalyticsService $analyticsService;

    public function boot(HRAnalyticsService $analyticsService): void
    {
        $this->analyticsService = $analyticsService;
    }

    public function mount(): void
    {
        $this->startDate = Carbon::now()->startOfYear()->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
        $this->leaveYear = Carbon::now()->year;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Date Range Filter')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start Date')
                            ->default(Carbon::now()->startOfYear())
                            ->live(),
                        DatePicker::make('endDate')
                            ->label('End Date')
                            ->default(Carbon::now())
                            ->live(),
                    ]),
            ]);
    }

    public function getSubheading(): ?string
    {
        return 'Key HR metrics and workforce analytics';
    }

    public function getHeadcountByDepartment(): array
    {
        return $this->analyticsService->getHeadcountByDepartment()->toArray();
    }

    public function getHeadcountByEmploymentStatus(): array
    {
        return $this->analyticsService->getHeadcountByEmploymentStatus()->toArray();
    }

    public function getTurnoverRate(): float
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        return $this->analyticsService->calculateTurnoverRate($start, $end);
    }

    public function getAgeDistribution(): array
    {
        return $this->analyticsService->getAgeDistribution();
    }

    public function getTenureDistribution(): array
    {
        return $this->analyticsService->getTenureDistribution();
    }

    public function getLeaveUsageSummary(): array
    {
        return $this->analyticsService->getLeaveUsageSummary($this->leaveYear)->toArray();
    }

    public function getEmployeeLeaveUsageDetails(): array
    {
        return $this->analyticsService->getEmployeeLeaveUsageDetails($this->leaveYear)->toArray();
    }

    public function getAttendanceKPIs(): array
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        return $this->analyticsService->getAttendanceKPIs($start, $end);
    }

    public function getRecruitmentMetrics(): array
    {
        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        return $this->analyticsService->getRecruitmentMetrics($start, $end);
    }

    public function getTotalHeadcount(): int
    {
        $headcount = $this->getHeadcountByDepartment();
        return array_sum(array_column($headcount, 'count'));
    }

    public function getAgeDistributionLabels(): array
    {
        return [
            'under_25' => 'Under 25',
            '25_34' => '25-34',
            '35_44' => '35-44',
            '45_54' => '45-54',
            '55_plus' => '55+',
        ];
    }

    public function getTenureDistributionLabels(): array
    {
        return [
            'less_than_1_year' => '< 1 Year',
            '1_2_years' => '1-2 Years',
            '3_5_years' => '3-5 Years',
            '6_10_years' => '6-10 Years',
            'more_than_10_years' => '> 10 Years',
        ];
    }

    public function getEmploymentStatusLabel(string $status): string
    {
        return match ($status) {
            'permanent' => 'Permanent',
            'contract' => 'Contract',
            'probation' => 'Probation',
            'intern' => 'Intern',
            default => ucfirst($status),
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportHeadcount')
                ->label('Export Headcount')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(
                        new HeadcountExport(),
                        'headcount-report-' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
            Action::make('exportTurnover')
                ->label('Export Turnover')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->action(function () {
                    $start = Carbon::parse($this->startDate);
                    $end = Carbon::parse($this->endDate);
                    return Excel::download(
                        new TurnoverExport($start, $end),
                        'turnover-report-' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
            Action::make('exportRecruitment')
                ->label('Export Recruitment')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->action(function () {
                    $start = Carbon::parse($this->startDate);
                    $end = Carbon::parse($this->endDate);
                    return Excel::download(
                        new RecruitmentReportExport($start, $end),
                        'recruitment-report-' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
        ];
    }
}
