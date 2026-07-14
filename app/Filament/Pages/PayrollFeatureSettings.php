<?php

namespace App\Filament\Pages;

use App\Models\Hris\PayrollSetting;
use App\Services\Hris\PayrollSettingService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PayrollFeatureSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Feature Settings';

    protected static ?string $title = 'Payroll Feature Settings';

    protected static string $view = 'filament.pages.payroll-feature-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'segmentation_enabled' => app(PayrollSettingService::class)->isSalarySegmentationEnabled(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Monthly base salary')
                    ->description('Effective-date salary segmentation splits a payroll period at salary change dates, so a mid-period raise or decrease is prorated by scheduled working days instead of applying the latest rate to the whole month.')
                    ->schema([
                        Toggle::make('segmentation_enabled')
                            ->label('Effective-date salary segmentation')
                            ->helperText('When ON, monthly base salary is segmented by effective date. If an employee\'s salary history does not cover their employed days, payroll calculation for that period is blocked until it is fixed.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        PayrollSetting::setValue(
            'salary_effective_date_segmentation',
            ['enabled' => (bool) ($this->form->getState()['segmentation_enabled'] ?? false)],
            PayrollSetting::CATEGORY_GENERAL,
            'Effective-date salary segmentation',
        );

        Notification::make()->title('Payroll feature settings saved')->success()->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
