<?php

namespace App\Filament\Resources\PayrunBatchResource\RelationManagers;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PayrollRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrollRecords';

    protected static ?string $title = 'Payroll Records';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('basic_salary')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_income')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_bpjs_employee')
                    ->label('BPJS (Employee)')
                    ->tooltip('Badan Penyelenggara Jaminan Sosial - Potongan iuran BPJS dari gaji karyawan')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('pph21_amount')
                    ->label('PPh 21')
                    ->tooltip('Pajak Penghasilan Pasal 21 - Potongan pajak penghasilan dari gaji')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('total_deductions')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('net_income')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View')
                    ->modalHeading(fn (Model $record) => 'Payslip — ' . ($record->employee?->user?->name ?? 'Employee'))
                    ->modalWidth('4xl')
                    ->infolist(fn (Infolist $infolist) => $this->payslipInfolist($infolist)),
            ])
            ->bulkActions([]);
    }

    protected function payslipInfolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ── Attendance & Prorate ──
            Section::make('Attendance & Prorate')
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('total_working_days')
                            ->label('Working days')
                            ->suffix(' days'),
                        TextEntry::make('actual_days_worked')
                            ->label('Days worked')
                            ->suffix(' days'),
                        TextEntry::make('prorate_label')
                            ->label('Prorate'),
                        TextEntry::make('prorate_percentage')
                            ->label('Prorate %')
                            ->suffix('%'),
                    ]),
                ])
                ->visible(fn (Model $record) => $record->details['prorate_enabled'] ?? false),

            // ── Earnings ──
            Section::make('Earnings')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('full_basic_salary')
                            ->label('Full basic salary')
                            ->money('IDR')
                            ->visible(fn (Model $record) => $record->isProrated()),
                        TextEntry::make('basic_salary')
                            ->label(fn (Model $record) => $record->isProrated() ? 'Prorated basic salary' : 'Basic salary')
                            ->money('IDR'),
                    ]),

                    // Fixed allowances (itemized)
                    Section::make('Fixed Allowances')
                        ->schema([
                            TextEntry::make('fixed_allowances_breakdown')
                                ->label('')
                                ->getStateUsing(function (Model $record) {
                                    $items = $record->details['allowances_fixed'] ?? [];
                                    if (empty($items)) {
                                        return 'No fixed allowances';
                                    }
                                    return collect($items)->map(function ($item) {
                                        $name = $item['name'] ?? 'Unnamed';
                                        $amount = number_format((float) ($item['amount'] ?? 0), 0, ',', '.');
                                        return "- {$name}: IDR {$amount}";
                                    })->join("\n");
                                })
                                ->markdown(),
                            TextEntry::make('total_fixed_allowances')
                                ->label('Total fixed allowances')
                                ->money('IDR'),
                        ])
                        ->compact()
                        ->visible(fn (Model $record) => !empty($record->details['allowances_fixed'] ?? [])),

                    // Variable allowances (itemized)
                    Section::make('Variable Allowances (Attendance-based)')
                        ->schema([
                            TextEntry::make('variable_allowances_breakdown')
                                ->label('')
                                ->getStateUsing(function (Model $record) {
                                    $items = $record->details['allowances_variable'] ?? [];
                                    if (empty($items)) {
                                        return 'No variable allowances';
                                    }
                                    return collect($items)->map(function ($item) {
                                        $name = $item['name'] ?? 'Unnamed';
                                        $amount = number_format((float) ($item['amount'] ?? 0), 0, ',', '.');
                                        $days = $item['days'] ?? null;
                                        $rate = $item['rate'] ?? null;
                                        $extra = '';
                                        if ($rate !== null && $days !== null) {
                                            $rateFormatted = number_format((float) $rate, 0, ',', '.');
                                            $extra = " ({$days} days × IDR {$rateFormatted})";
                                        }
                                        return "- {$name}: IDR {$amount}{$extra}";
                                    })->join("\n");
                                })
                                ->markdown(),
                            TextEntry::make('total_variable_allowances')
                                ->label('Total variable allowances')
                                ->money('IDR'),
                        ])
                        ->compact()
                        ->visible(fn (Model $record) => (float) $record->total_variable_allowances > 0),

                    // Daily allowance
                    Section::make('Daily Allowance (Uang Harian)')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('daily_allowance_rate_display')
                                    ->label('Rate per day')
                                    ->getStateUsing(fn (Model $record) => $record->details['daily_allowance_rate'] ?? 0)
                                    ->money('IDR'),
                                TextEntry::make('actual_days_worked')
                                    ->label('Days worked')
                                    ->suffix(' days'),
                                TextEntry::make('daily_allowance_total')
                                    ->label('Total daily allowance')
                                    ->money('IDR'),
                            ]),
                        ])
                        ->compact()
                        ->visible(fn (Model $record) => (float) ($record->daily_allowance_total ?? 0) > 0),

                    // Weekly allowance reconciliation
                    Section::make('Weekly Allowance Reconciliation')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('weekly_allowance_earned')
                                    ->label('Earned this month')
                                    ->money('IDR'),
                                TextEntry::make('weekly_allowance_paid')
                                    ->label('Already paid (weekly)')
                                    ->money('IDR'),
                                TextEntry::make('weekly_allowance_adjustment')
                                    ->label('Adjustment')
                                    ->money('IDR')
                                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success'),
                            ]),
                        ])
                        ->compact()
                        ->visible(fn (Model $record) => $record->details['weekly_allowance_enabled'] ?? false),

                    // Gross income total
                    TextEntry::make('gross_income')
                        ->label('Gross Income')
                        ->money('IDR')
                        ->weight('bold')
                        ->size(TextEntry\TextEntrySize::Large),
                ]),

            // ── Deductions ──
            Section::make('Deductions')
                ->icon('heroicon-o-minus-circle')
                ->schema([
                    // BPJS employee deductions
                    Section::make('BPJS (Employee Contributions)')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('bpjs_kesehatan_employee')
                                    ->label('Kesehatan')
                                    ->money('IDR'),
                                TextEntry::make('bpjs_jht_employee')
                                    ->label('JHT')
                                    ->money('IDR'),
                                TextEntry::make('bpjs_jp_employee')
                                    ->label('JP')
                                    ->money('IDR'),
                            ]),
                            TextEntry::make('total_bpjs_employee')
                                ->label('Total BPJS (Employee)')
                                ->money('IDR')
                                ->weight('bold'),
                        ])
                        ->compact()
                        ->visible(fn (Model $record) => (float) $record->total_bpjs_employee > 0),

                    // PPh 21
                    Grid::make(2)->schema([
                        TextEntry::make('pph21_amount')
                            ->label('PPh 21')
                            ->money('IDR'),
                        TextEntry::make('pph21_method_display')
                            ->label('PPh 21 Method')
                            ->getStateUsing(fn (Model $record) => match ($record->details['pph21_method'] ?? null) {
                                'netto' => 'Netto',
                                'gross' => 'Gross',
                                'gross_up' => 'Gross Up',
                                default => '-',
                            }),
                    ]),

                    // Other deductions (itemized)
                    Section::make('Other Deductions')
                        ->schema([
                            TextEntry::make('other_deductions_breakdown')
                                ->label('')
                                ->getStateUsing(function (Model $record) {
                                    $items = $record->details['deductions'] ?? [];
                                    if (empty($items)) {
                                        return 'No other deductions';
                                    }
                                    return collect($items)->map(function ($item) {
                                        $name = $item['name'] ?? 'Unnamed';
                                        $amount = number_format((float) ($item['amount'] ?? 0), 0, ',', '.');
                                        return "- {$name}: IDR {$amount}";
                                    })->join("\n");
                                })
                                ->markdown(),
                            TextEntry::make('other_deductions')
                                ->label('Total other deductions')
                                ->money('IDR'),
                        ])
                        ->compact()
                        ->visible(fn (Model $record) => (float) $record->other_deductions > 0),

                    // Total deductions
                    TextEntry::make('total_deductions')
                        ->label('Total Deductions')
                        ->money('IDR')
                        ->weight('bold')
                        ->size(TextEntry\TextEntrySize::Large)
                        ->color('danger'),
                ]),

            // ── Net Income ──
            Section::make('Net Income')
                ->icon('heroicon-o-currency-dollar')
                ->schema([
                    TextEntry::make('net_income')
                        ->label('Take-home Pay')
                        ->money('IDR')
                        ->weight('bold')
                        ->size(TextEntry\TextEntrySize::Large)
                        ->color('success'),
                ]),

            // ── Company Contributions (informational) ──
            Section::make('Company Contributions')
                ->icon('heroicon-o-building-office')
                ->description('Paid by the company on top of employee salary')
                ->schema([
                    Grid::make(5)->schema([
                        TextEntry::make('bpjs_kesehatan_company')
                            ->label('Kesehatan')
                            ->money('IDR'),
                        TextEntry::make('bpjs_jht_company')
                            ->label('JHT')
                            ->money('IDR'),
                        TextEntry::make('bpjs_jp_company')
                            ->label('JP')
                            ->money('IDR'),
                        TextEntry::make('bpjs_jkk_company')
                            ->label('JKK')
                            ->money('IDR'),
                        TextEntry::make('bpjs_jkm_company')
                            ->label('JKM')
                            ->money('IDR'),
                    ]),
                    TextEntry::make('total_bpjs_company')
                        ->label('Total Company BPJS')
                        ->money('IDR')
                        ->weight('bold'),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (Model $record) => (float) $record->total_bpjs_company > 0),
        ]);
    }
}
