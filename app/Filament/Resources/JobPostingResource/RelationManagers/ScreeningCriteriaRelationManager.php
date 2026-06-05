<?php

namespace App\Filament\Resources\JobPostingResource\RelationManagers;

use App\Models\Hris\Applicant;
use App\Services\Hris\Screening\SensitiveCriteriaGuard;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ScreeningCriteriaRelationManager extends RelationManager
{
    protected static string $relationship = 'screeningCriteria';

    protected static ?string $title = 'Screening Criteria';

    protected static ?string $icon = 'heroicon-o-adjustments-horizontal';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewScreening', Applicant::class) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('group')
                ->options([
                    'must_have' => 'Must-have',
                    'nice_to_have' => 'Nice-to-have',
                    'fit' => 'Fit',
                    'knockout' => 'Knockout',
                ])
                ->required()
                ->live(),
            Forms\Components\TextInput::make('label')
                ->required()
                ->maxLength(255)
                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                    if ($hit = app(SensitiveCriteriaGuard::class)->firstHit($value)) {
                        $fail("Criteria may not reference the protected attribute \"{$hit}\".");
                    }
                }),
            Forms\Components\TagsInput::make('keywords')
                ->helperText('Words to look for in the CV.')
                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                    if ($hit = app(SensitiveCriteriaGuard::class)->firstHitInList((array) $value)) {
                        $fail("Keywords may not reference the protected attribute \"{$hit}\".");
                    }
                }),
            Forms\Components\TagsInput::make('synonyms')
                ->helperText('Optional alternates that also satisfy this criterion.')
                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                    if ($hit = app(SensitiveCriteriaGuard::class)->firstHitInList((array) $value)) {
                        $fail("Synonyms may not reference the protected attribute \"{$hit}\".");
                    }
                }),
            Forms\Components\TextInput::make('weight')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required(),
            Forms\Components\Toggle::make('missing_means_failed')
                ->label('Missing means failed (knockout only)')
                ->visible(fn (Forms\Get $get): bool => $get('group') === 'knockout')
                ->default(false),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->badge()->sortable(),
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('weight')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $data + ['updated_by' => auth()->id()]),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
