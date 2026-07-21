<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use SolutionForest\AiKit\Enums\DocumentStatus;
use SolutionForest\AiKit\Filament\Resources\DocumentResource\Pages;
use SolutionForest\AiKit\Ingestion\IngestionService;
use SolutionForest\AiKit\Models\Document;

final class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string | \UnitEnum | null $navigationGroup = 'AI Kit';

    protected static ?string $navigationLabel = 'Documents';

    protected static ?int $navigationSort = 2;

    /** Upload types accepted for ingestion (pdf, text, html, markdown, Word, Excel). */
    public const ACCEPTED_MIMES = [
        'application/pdf',
        'text/plain',
        'text/markdown',
        'text/html',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',       // .xlsx
    ];

    // Documents are managed inline under each Collection (relation manager), so
    // this resource is kept for its routes/upload logic but hidden from the menu.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('collection_id')
                ->relationship('collection', 'name')
                ->required()
                ->searchable()
                ->preload(),
            TextInput::make('title')
                ->maxLength(255)
                ->helperText('Leave blank to use the uploaded file name.'),
            FileUpload::make('source_upload')
                ->label('File')
                ->disk('local')
                ->directory('ai-kit/uploads')
                ->preserveFilenames()
                ->acceptedFileTypes(self::ACCEPTED_MIMES)
                ->required()
                ->dehydrated(false)
                ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(50),
                TextColumn::make('collection.name')->badge()->color('gray')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DocumentStatus $state): string => $state->label())
                    ->color(fn (DocumentStatus $state): string => $state->color()),
                TextColumn::make('chunk_count')->label('Chunks')->sortable(),
                TextColumn::make('created_at')->dateTime()->since()->sortable()->label('Uploaded'),
            ])
            ->recordActions([
                self::viewChunksAction(),
                self::reindexAction(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function viewChunksAction(): Action
    {
        return Action::make('viewChunks')
            ->label('Chunks')
            ->icon('heroicon-o-list-bullet')
            ->modalHeading(fn (Document $record): string => "Chunks — {$record->title}")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (Document $record) => view('ai-kit-filament::chunks', [
                'chunks' => $record->chunks()->orderBy('chunk_index')->get(),
            ]));
    }

    private static function reindexAction(): Action
    {
        return Action::make('reindex')
            ->label('Re-index')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->action(function (Document $record): void {
                app(IngestionService::class)->queue($record);
            })
            ->successNotificationTitle('Re-indexing queued.');
    }

    /**
     * Resolve an uploaded file into ingestible document attributes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareUpload(array $data): array
    {
        $relative = $data['source_upload'] ?? null;
        unset($data['source_upload']);

        if (is_array($relative)) {
            $relative = reset($relative);
        }

        if (is_string($relative) && $relative !== '') {
            $absolute = Storage::disk('local')->path($relative);
            $data['source_path'] = $absolute;
            $data['mime'] = Storage::disk('local')->mimeType($relative) ?: 'text/plain';
            $data['title'] = $data['title'] ?: pathinfo($relative, PATHINFO_FILENAME);
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
        ];
    }
}
