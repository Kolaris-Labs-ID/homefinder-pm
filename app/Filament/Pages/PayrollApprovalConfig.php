<?php

namespace App\Filament\Pages;

use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class PayrollApprovalConfig extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Payroll';
    protected static ?string $navigationLabel = 'Approval Configuration';
    protected static string $view = 'filament.pages.payroll-approval-config';

    public ?array $monthlyConfig = [];
    public ?array $weeklyConfig = [];

    public function mount(): void
    {
        $this->monthlyConfig = $this->loadConfigData(PayrollWorkflowConfig::MODULE_MONTHLY);
        $this->weeklyConfig = $this->loadConfigData(PayrollWorkflowConfig::MODULE_WEEKLY);
    }

    protected function loadConfigData(string $module): array
    {
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (! $config) {
            return [
                'finance_assignee_type' => null,
                'finance_assignee_user_id' => null,
                'finance_assignee_role' => null,
                'enable_coo_step' => false,
                'coo_assignee_type' => null,
                'coo_assignee_user_id' => null,
                'coo_assignee_role' => null,
            ];
        }

        $financeStep = $config->steps()->where('step_type', PayrollWorkflowStep::STEP_FINANCE)->first();
        $cooStep = $config->steps()->where('step_type', PayrollWorkflowStep::STEP_COO)->first();

        return [
            'finance_assignee_type' => $financeStep?->assignee_type,
            'finance_assignee_user_id' => $financeStep?->assignee_user_id,
            'finance_assignee_role' => $financeStep?->assignee_role,
            'enable_coo_step' => $cooStep !== null,
            'coo_assignee_type' => $cooStep?->assignee_type,
            'coo_assignee_user_id' => $cooStep?->assignee_user_id,
            'coo_assignee_role' => $cooStep?->assignee_role,
        ];
    }

    protected function getForms(): array
    {
        return [
            'monthlyForm' => $this->monthlyForm(
                $this->makeForm()->statePath('monthlyConfig'),
            ),
            'weeklyForm' => $this->weeklyForm(
                $this->makeForm()->statePath('weeklyConfig'),
            ),
        ];
    }

    public function monthlyForm(Form $form): Form
    {
        return $form->schema($this->buildStepSchema('monthly'));
    }

    public function weeklyForm(Form $form): Form
    {
        return $form->schema($this->buildStepSchema('weekly'));
    }

    protected function buildStepSchema(string $prefix): array
    {
        $userOptions = fn () => User::orderBy('name')->pluck('name', 'id')->toArray();
        $roleOptions = fn () => Role::orderBy('name')->pluck('name', 'name')->toArray();

        return [
            Section::make('Finance Approval Step')
                ->description('Configure the Finance approver for this module. This step is required.')
                ->schema([
                    Select::make('finance_assignee_type')
                        ->label('Assignee Type')
                        ->options([
                            'user' => 'Specific User',
                            'role' => 'Role',
                        ])
                        ->required()
                        ->live(),

                    Select::make('finance_assignee_user_id')
                        ->label('Assignee User')
                        ->options($userOptions)
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('finance_assignee_type') === 'user'),

                    Select::make('finance_assignee_role')
                        ->label('Assignee Role')
                        ->options($roleOptions)
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('finance_assignee_type') === 'role'),
                ]),

            Section::make('COO Approval Step')
                ->description('Optionally enable a COO escalation step. When enabled, Finance can escalate batches to COO for review.')
                ->schema([
                    Toggle::make('enable_coo_step')
                        ->label('Enable COO Step')
                        ->live(),

                    Select::make('coo_assignee_type')
                        ->label('Assignee Type')
                        ->options([
                            'user' => 'Specific User',
                            'role' => 'Role',
                        ])
                        ->required()
                        ->live()
                        ->visible(fn ($get) => $get('enable_coo_step')),

                    Select::make('coo_assignee_user_id')
                        ->label('Assignee User')
                        ->options($userOptions)
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('enable_coo_step') && $get('coo_assignee_type') === 'user'),

                    Select::make('coo_assignee_role')
                        ->label('Assignee Role')
                        ->options($roleOptions)
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('enable_coo_step') && $get('coo_assignee_type') === 'role'),
                ]),
        ];
    }

    public function saveMonthly(): void
    {
        $this->monthlyForm->getState();
        $this->persistConfig(PayrollWorkflowConfig::MODULE_MONTHLY, $this->monthlyConfig);
    }

    public function saveWeekly(): void
    {
        $this->weeklyForm->getState();
        $this->persistConfig(PayrollWorkflowConfig::MODULE_WEEKLY, $this->weeklyConfig);
    }

    protected function persistConfig(string $module, array $data): void
    {
        DB::transaction(function () use ($module, $data) {
            $config = PayrollWorkflowConfig::updateOrCreate(
                ['module' => $module],
                ['is_active' => true],
            );

            // Remove existing steps and recreate
            $config->steps()->delete();

            // Create finance step (always required)
            $config->steps()->create([
                'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                'assignee_type' => $data['finance_assignee_type'],
                'assignee_user_id' => $data['finance_assignee_type'] === 'user' ? $data['finance_assignee_user_id'] : null,
                'assignee_role' => $data['finance_assignee_type'] === 'role' ? $data['finance_assignee_role'] : null,
                'order' => 1,
            ]);

            // Create COO step if enabled
            if (! empty($data['enable_coo_step'])) {
                $config->steps()->create([
                    'step_type' => PayrollWorkflowStep::STEP_COO,
                    'assignee_type' => $data['coo_assignee_type'],
                    'assignee_user_id' => $data['coo_assignee_type'] === 'user' ? $data['coo_assignee_user_id'] : null,
                    'assignee_role' => $data['coo_assignee_type'] === 'role' ? $data['coo_assignee_role'] : null,
                    'order' => 2,
                ]);
            }
        });

        Notification::make()
            ->title('Configuration saved successfully.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
