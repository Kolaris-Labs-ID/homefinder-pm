<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollSettingResource\Pages;
use App\Models\Hris\PayrollSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollSettingResource extends Resource
{
    protected static ?string $model = PayrollSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?string $navigationLabel = 'Payroll Settings';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'Payroll Setting';

    protected static ?string $pluralModelLabel = 'Payroll Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Setting Information')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Unique identifier for this setting (e.g., bpjs_kes_employee_rate)')
                            ->regex('/^[a-z0-9_]+$/')
                            ->validationMessages([
                                'regex' => 'Key must contain only lowercase letters, numbers, and underscores.',
                            ]),
                        Forms\Components\TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable label for display'),
                        Forms\Components\Select::make('category')
                            ->options(PayrollSetting::getCategoryLabels())
                            ->required()
                            ->default(PayrollSetting::CATEGORY_GENERAL),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Setting Value')
                    ->schema([
                        Forms\Components\KeyValue::make('value')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->required()
                            ->helperText('Enter the setting value as key-value pairs (e.g., rate: 0.01, amount: 12000000)')
                            ->addActionLabel('Add Property')
                            ->reorderable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PayrollSetting::CATEGORY_BPJS => 'success',
                        PayrollSetting::CATEGORY_TAX => 'warning',
                        PayrollSetting::CATEGORY_GENERAL => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return collect($state)
                                ->map(fn ($v, $k) => "{$k}: " . (is_numeric($v) && $v < 1 ? ($v * 100) . '%' : number_format($v)))
                                ->join(', ');
                        }
                        return $state;
                    })
                    ->wrap()
                    ->limit(50),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('category')
            ->groups([
                Tables\Grouping\Group::make('category')
                    ->label('Category')
                    ->getTitleFromRecordUsing(fn (PayrollSetting $record): string => 
                        PayrollSetting::getCategoryLabels()[$record->category] ?? $record->category
                    ),
            ])
            ->defaultGroup('category')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(PayrollSetting::getCategoryLabels()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollSettings::route('/'),
            'create' => Pages\CreatePayrollSetting::route('/create'),
            'view' => Pages\ViewPayrollSetting::route('/{record}'),
            'edit' => Pages\EditPayrollSetting::route('/{record}/edit'),
        ];
    }
}
