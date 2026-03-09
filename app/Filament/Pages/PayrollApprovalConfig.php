<?php

namespace App\Filament\Pages;

use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

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
                'finance_approver_user_ids' => [],
            ];
        }

        $financeUserIds = $config->steps()
            ->where('step_type', PayrollWorkflowStep::STEP_FINANCE)
            ->pluck('assignee_user_id')
            ->filter()
            ->values()
            ->toArray();

        return [
            'finance_approver_user_ids' => $financeUserIds,
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
        return $form->schema($this->buildStepSchema());
    }

    public function weeklyForm(Form $form): Form
    {
        return $form->schema($this->buildStepSchema());
    }

    protected function buildStepSchema(): array
    {
        $userOptions = fn () => User::orderBy('name')->pluck('name', 'id')->toArray();

        return [
            Section::make('Finance Approvers')
                ->description('Select the finance users who can approve and pay batches. Any one of them can approve (first wins). All will see the batch and who paid.')
                ->schema([
                    Select::make('finance_approver_user_ids')
                        ->label('Finance Approvers')
                        ->options($userOptions)
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->minItems(1)
                        ->helperText('Select at least one finance approver. Any of them can approve, reject, or pay.'),
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

            // Create one finance step row per selected user
            $userIds = $data['finance_approver_user_ids'] ?? [];
            foreach ($userIds as $order => $userId) {
                $config->steps()->create([
                    'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                    'assignee_type' => 'user',
                    'assignee_user_id' => $userId,
                    'assignee_role' => null,
                    'order' => $order + 1,
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
