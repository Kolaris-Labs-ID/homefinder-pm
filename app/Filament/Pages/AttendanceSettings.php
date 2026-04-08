<?php

namespace App\Filament\Pages;

use App\Models\Hris\AttendanceSetting;
use App\Models\Hris\AttendanceSettingAudit;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class AttendanceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Attendance';
    protected static ?string $navigationLabel = 'Attendance Settings';
    protected static string $view = 'filament.pages.attendance-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = AttendanceSetting::getSettings();

        $this->form->fill([
            'work_start_time' => $settings->work_start_time,
            'late_grace_period_minutes' => $settings->late_grace_period_minutes,
            'wfh_quota_per_week' => $settings->wfh_quota_per_week,
            'full_day_hours' => $settings->full_day_hours,
            'half_day_hours' => $settings->half_day_hours,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Work Schedule')
                    ->columns(2)
                    ->schema([
                        TimePicker::make('work_start_time')
                            ->label('Work Start Time')
                            ->required()
                            ->seconds(false)
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    if (empty($value)) {
                                        return;
                                    }
                                    $time = date('H:i', strtotime($value));
                                    if ($time < '06:00' || $time > '12:00') {
                                        $fail('Work start time must be between 06:00 and 12:00.');
                                    }
                                },
                            ])
                            ->validationMessages([
                                'required' => 'Work start time is required.',
                            ]),

                        TextInput::make('late_grace_period_minutes')
                            ->label('Late Grace Period')
                            ->numeric()
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60)
                            ->suffix('minutes')
                            ->validationMessages([
                                'required' => 'Late grace period is required.',
                                'integer' => 'Late grace period must be a whole number.',
                                'min' => 'Late grace period must be at least 0 minutes.',
                                'max' => 'Late grace period must not exceed 60 minutes.',
                            ]),
                    ]),

                Section::make('Work From Home')
                    ->schema([
                        TextInput::make('wfh_quota_per_week')
                            ->label('WFH Quota Per Week')
                            ->numeric()
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(5)
                            ->suffix('days per week')
                            ->validationMessages([
                                'required' => 'WFH quota per week is required.',
                                'integer' => 'WFH quota must be a whole number.',
                                'min' => 'WFH quota must be at least 0 days per week.',
                                'max' => 'WFH quota must not exceed 5 days per week.',
                            ]),
                    ]),

                Section::make('Late Penalty Tiers')
                    ->description('Define deduction tiers for employees arriving late without an approved late notice. Minutes are counted from the scheduled work start time.')
                    ->schema([
                        Repeater::make('late_penalty_tiers')
                            ->label('')
                            ->columns(2)
                            ->addActionLabel('Add Tier')
                            ->schema([
                                TextInput::make('minutes_late')
                                    ->label('Minutes Late (from start time)')
                                    ->numeric()
                                    ->integer()
                                    ->required()
                                    ->minValue(1)
                                    ->suffix('min'),

                                TextInput::make('deduction_percent')
                                    ->label('Deduction')
                                    ->numeric()
                                    ->integer()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->suffix('%'),
                            ])
                            ->reorderable()
                            ->reorderableWithDragAndDrop(),
                    ]),

                Section::make('Day Duration')
                    ->columns(2)
                    ->schema([
                        TextInput::make('full_day_hours')
                            ->label('Full Day Hours')
                            ->numeric()
                            ->required()
                            ->minValue(4)
                            ->maxValue(12)
                            ->suffix('hours')
                            ->validationMessages([
                                'required' => 'Full day hours is required.',
                                'numeric' => 'Full day hours must be a number.',
                                'min' => 'Full day hours must be at least 4 hours.',
                                'max' => 'Full day hours must not exceed 12 hours.',
                            ]),

                        TextInput::make('half_day_hours')
                            ->label('Half Day Hours')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(12)
                            ->suffix('hours')
                            ->rules([
                                fn (callable $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $fullDayHours = $get('full_day_hours');
                                    if ($value !== null && $fullDayHours !== null && (float) $value >= (float) $fullDayHours) {
                                        $fail('Half day hours must be less than full day hours.');
                                    }
                                },
                            ])
                            ->validationMessages([
                                'required' => 'Half day hours is required.',
                                'numeric' => 'Half day hours must be a number.',
                                'min' => 'Half day hours must be at least 1 hour.',
                                'max' => 'Half day hours must not exceed 12 hours.',
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Save settings with audit logging.
     */
    public function save(): void
    {
        $data = $this->form->getState();
        $settings = AttendanceSetting::getSettings();

        $fields = [
            'work_start_time',
            'late_grace_period_minutes',
            'wfh_quota_per_week',
            'full_day_hours',
            'half_day_hours',
            'late_penalty_tiers',
        ];

        // Normalize work_start_time to HH:MM for comparison
        $submittedWorkStartTime = isset($data['work_start_time'])
            ? date('H:i', strtotime($data['work_start_time']))
            : null;

        $changes = [];
        foreach ($fields as $field) {
            if ($field === 'late_penalty_tiers') {
                $oldValue = json_encode($settings->{$field} ?? []);
                $newValue = json_encode(array_values((array) ($data[$field] ?? [])));
            } elseif ($field === 'work_start_time') {
                $oldValue = (string) $settings->{$field};
                $newValue = $submittedWorkStartTime;
            } else {
                $oldValue = (string) $settings->{$field};
                $newValue = (string) $data[$field];
            }

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field'     => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ];
            }
        }

        DB::transaction(function () use ($settings, $data, $changes, $submittedWorkStartTime) {
            foreach ($changes as $change) {
                AttendanceSettingAudit::create([
                    'user_id' => auth()->id(),
                    'field' => $change['field'],
                    'old_value' => $change['old_value'],
                    'new_value' => $change['new_value'],
                ]);
            }

            $settings->update([
                'work_start_time'          => $submittedWorkStartTime,
                'late_grace_period_minutes' => $data['late_grace_period_minutes'],
                'wfh_quota_per_week'       => $data['wfh_quota_per_week'],
                'full_day_hours'           => $data['full_day_hours'],
                'half_day_hours'           => $data['half_day_hours'],
                'late_penalty_tiers'       => array_values((array) ($data['late_penalty_tiers'] ?? [])),
            ]);
        });

        Notification::make()
            ->title(count($changes) > 0 ? 'Settings updated successfully.' : 'No changes detected.')
            ->success()
            ->send();
    }

    /**
     * Get recent audit log entries for display in the change history table.
     */
    public function getAuditLogs(): Collection
    {
        return AttendanceSettingAudit::with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /**
     * Authorization check: only users with view_attendance_setting permission can access.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_attendance_setting') ?? false;
    }

}
