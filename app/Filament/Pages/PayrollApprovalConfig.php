<?php

namespace App\Filament\Pages;

use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\User;
use Filament\Forms\Components\Repeater;
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
                'approver_steps' => [],
            ];
        }

        $steps = $config->steps()
            ->where('step_type', PayrollWorkflowStep::STEP_FINANCE)
            ->orderBy('order', 'asc')
            ->get();

        return [
            'approver_steps' => $steps->map(fn ($step) => [
                'user_id' => $step->assignee_user_id,
            ])->values()->toArray(),
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
            Section::make('Sequential Finance Approvers')
                ->description('Define the approval chain in order. Each approver must review the payroll records and approve before the batch moves to the next. The last approver will see the "Approve & Pay" button.')
                ->schema([
                    Repeater::make('approver_steps')
                        ->label('Approval Steps')
                        ->schema([
                            Select::make('user_id')
                                ->label('Approver')
                                ->options($userOptions)
                                ->searchable()
                                ->required()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        ])
                        ->reorderable()
                        ->reorderableWithDragAndDrop()
                        ->orderColumn(false)
                        ->addActionLabel('Add approver step')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->itemNumbers()
                        ->helperText('Drag to reorder. Step 1 approves first, last step can approve and pay.'),
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

            $config->steps()->delete();

            $steps = $data['approver_steps'] ?? [];
            foreach (array_values($steps) as $order => $step) {
                $config->steps()->create([
                    'step_type' => PayrollWorkflowStep::STEP_FINANCE,
                    'assignee_type' => 'user',
                    'assignee_user_id' => $step['user_id'],
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
