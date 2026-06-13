<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectDocument;
use App\Services\ProjectDocumentStorageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    protected static ?string $modelLabel = 'Document';

    protected static ?string $pluralModelLabel = 'Documents';

    protected static ?string $icon = 'heroicon-o-document-text';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('upload')
                ->label('File (PDF, Office, or image · max 10 MB)')
                ->disk('private')
                ->visibility('private')
                ->storeFiles(false)
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'image/png',
                    'image/jpeg',
                ])
                ->maxSize(10240)
                ->required()
                ->columnSpanFull(),
            Forms\Components\Select::make('category')
                ->options(self::categoryOptions())
                ->required()
                ->searchable(),
            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->maxLength(2000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category_label')
                    ->label('Category')
                    ->badge()
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('category', $direction)),
                Tables\Columns\TextColumn::make('formatted_size')
                    ->label('Size'),
                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded by')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('uploaded_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_archived')
                    ->label('Archived')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(self::categoryOptions()),
                Tables\Filters\TernaryFilter::make('archived')
                    ->label('Archive status')
                    ->placeholder('Active only')
                    ->trueLabel('Archived only')
                    ->falseLabel('Active only')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('archived_at'),
                        false: fn ($query) => $query->whereNull('archived_at'),
                        blank: fn ($query) => $query->whereNull('archived_at'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Upload Document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalWidth('xl')
                    ->using(fn (array $data, RelationManager $livewire): ProjectDocument => app(ProjectDocumentStorageService::class)->store(
                        $livewire->getOwnerRecord(),
                        $data['upload'],
                        $data['category'],
                        $data['description'] ?? null,
                        auth()->user(),
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (ProjectDocument $record): string => route('projects.documents.view', [$record->project_id, $record->id]))
                    ->openUrlInNewTab()
                    ->visible(fn (ProjectDocument $record): bool => in_array($record->mime_type, ['application/pdf', 'image/png', 'image/jpeg'], true)
                        && auth()->user()->can('view', $record)),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (ProjectDocument $record): string => route('projects.documents.download', [$record->project_id, $record->id]))
                    ->openUrlInNewTab()
                    ->visible(fn (ProjectDocument $record): bool => auth()->user()->can('download', $record)),
                Tables\Actions\Action::make('archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ProjectDocument $record): bool => ! $record->is_archived && auth()->user()->can('delete', $record))
                    ->action(fn (ProjectDocument $record) => app(ProjectDocumentStorageService::class)->archive($record, auth()->user())),
            ])
            ->defaultSort('uploaded_at', 'desc')
            ->emptyStateHeading('No documents yet')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    private static function categoryOptions(): array
    {
        return collect(ProjectDocument::CATEGORIES)
            ->mapWithKeys(fn (string $c): array => [$c => ProjectDocument::CATEGORY_LABELS[$c]])
            ->all();
    }
}
