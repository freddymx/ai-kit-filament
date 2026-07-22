<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources\CollectionResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SolutionForest\AiKit\Enums\DocumentStatus;
use SolutionForest\AiKit\Filament\Resources\DocumentResource;
use SolutionForest\AiKit\Ingestion\IngestionService;
use SolutionForest\AiKit\Models\Document;

/**
 * Lists a collection's documents inline on the collection's edit page, with
 * view-chunks and re-index actions.
 */
final class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(50),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DocumentStatus $state): string => $state->label())
                    ->color(fn (DocumentStatus $state): string => $state->color()),
                TextColumn::make('chunk_count')->label('Chunks')->sortable(),
                TextColumn::make('mime')->label('Type')->badge()->color('gray')->toggleable(),
                TextColumn::make('created_at')->dateTime()->since()->sortable()->label('Uploaded'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Upload document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        TextInput::make('title')
                            ->maxLength(255)
                            ->helperText('Leave blank to use the uploaded file name.'),
                        FileUpload::make('source_upload')
                            ->label('File (pdf, txt, md, html, Word, Excel)')
                            ->disk('local')
                            ->directory('ai-kit/uploads')
                            ->preserveFilenames()
                            ->acceptedFileTypes(DocumentResource::ACCEPTED_MIMES)
                            ->required(),
                        // Keep source_upload dehydrated so mutateDataUsing() /
                        // prepareUpload() can read it; prepareUpload unsets it
                        // before the record is created.
                    ])
                    ->mutateDataUsing(fn (array $data): array => DocumentResource::prepareUpload($data))
                    ->after(fn (Document $record) => app(IngestionService::class)->queue($record))
                    ->successNotificationTitle('Uploaded — ingestion queued.'),

                Action::make('addFromUrl')
                    ->label('Add from URL')
                    ->icon('heroicon-o-link')
                    ->schema([
                        TextInput::make('url')
                            ->label('Page URL')
                            ->url()
                            ->required()
                            ->placeholder('https://example.com/article'),
                        TextInput::make('title')
                            ->maxLength(255)
                            ->helperText('Leave blank to use the page title.'),
                    ])
                    ->action(function (array $data): void {
                        app(IngestionService::class)->createFromUrl(
                            $this->getOwnerRecord(),
                            $data['url'],
                            $data['title'] ?: null,
                            sync: false,
                        );
                    })
                    ->successNotificationTitle('Fetched — ingestion queued.'),
            ])
            ->recordActions([
                Action::make('viewChunks')
                    ->label('Chunks')
                    ->icon('heroicon-o-list-bullet')
                    ->modalHeading(fn (Document $record): string => "Chunks — {$record->title}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (Document $record) => view('ai-kit-filament::chunks', [
                        'chunks' => $record->chunks()->orderBy('chunk_index')->get(),
                    ])),
                Action::make('reindex')
                    ->label('Re-index')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(fn (Document $record) => app(IngestionService::class)->queue($record))
                    ->successNotificationTitle('Re-indexing queued.'),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No documents yet')
            ->emptyStateDescription('Upload documents in the Documents resource, or run php artisan ai-kit:ingest.');
    }
}
