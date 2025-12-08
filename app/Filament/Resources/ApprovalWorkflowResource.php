<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalWorkflowResource\Pages;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApprovalWorkflowResource extends Resource
{
    protected static ?string $model = ApprovalWorkflow::class;

    protected static ?string $navigationGroup = 'HRIS';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Approval Workflows';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Workflow Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Workflow Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Leave Request Approval'),
                        Forms\Components\Select::make('for_module')
                            ->label('Module')
                            ->options([
                                ApprovalWorkflow::MODULE_LEAVE => 'Leave Request',
                                ApprovalWorkflow::MODULE_REIMBURSEMENT => 'Reimbursement',
                                ApprovalWorkflow::MODULE_PAYROLL_ADJUSTMENT => 'Payroll Adjustment',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active workflows will be used for new requests'),
                    ]),

                Forms\Components\Section::make('Approval Steps')
                    ->description('Define the approval chain. Steps will be executed in order.')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->label('')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('order')
                                            ->label('Step Order')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                            ->default(fn ($get) => 1),
                                        Forms\Components\Select::make('type')
                                            ->label('Approver Type')
                                            ->options([
                                                'role' => 'By Role',
                                                'user' => 'Specific User',
                                            ])
                                            ->required()
                                            ->reactive()
                                            ->native(false),
                                        Forms\Components\Select::make('value')
                                            ->label('Approver')
                                            ->required()
                                            ->options(function (callable $get) {
                                                $type = $get('type');
                                                if ($type === 'role') {
                                                    return [
                                                        'head_division' => 'Head of Division',
                                                        'hr-admin' => 'HR Admin',
                                                        'hr-staff' => 'HR Staff',
                                                        'manager' => 'Manager',
                                                        'super_admin' => 'Super Admin',
                                                    ];
                                                } elseif ($type === 'user') {
                                                    return User::pluck('name', 'id')->toArray();
                                                }

                                                return [];
                                            })
                                            ->searchable()
                                            ->native(false),
                                    ]),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Add Step')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => isset($state['order']) ? "Step {$state['order']}" : null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Workflow Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('for_module')
                    ->label('Module')
                    ->colors([
                        'primary' => ApprovalWorkflow::MODULE_LEAVE,
                        'success' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
                        'warning' => ApprovalWorkflow::MODULE_PAYROLL_ADJUSTMENT,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ApprovalWorkflow::MODULE_LEAVE => 'Leave Request',
                        ApprovalWorkflow::MODULE_REIMBURSEMENT => 'Reimbursement',
                        ApprovalWorkflow::MODULE_PAYROLL_ADJUSTMENT => 'Payroll Adjustment',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('steps')
                    ->label('Steps')
                    ->formatStateUsing(fn (?array $state): string => $state ? count($state) . ' step(s)' : '0 steps')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('for_module')
                    ->label('Module')
                    ->options([
                        ApprovalWorkflow::MODULE_LEAVE => 'Leave Request',
                        ApprovalWorkflow::MODULE_REIMBURSEMENT => 'Reimbursement',
                        ApprovalWorkflow::MODULE_PAYROLL_ADJUSTMENT => 'Payroll Adjustment',
                    ]),
                Tables\Filters\Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn ($query) => $query->where('is_active', true))
                    ->toggle(),
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
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalWorkflows::route('/'),
            'create' => Pages\CreateApprovalWorkflow::route('/create'),
            'edit' => Pages\EditApprovalWorkflow::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_active', true)->count();
    }
}
