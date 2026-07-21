<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SolutionForest\AiKit\Filament\Resources\CollectionResource\Pages;
use SolutionForest\AiKit\Filament\Resources\CollectionResource\RelationManagers\DocumentsRelationManager;
use SolutionForest\AiKit\Models\Collection;

final class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'AI Kit';

    protected static ?string $navigationLabel = 'Collections';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $state, callable $set, ?Collection $record): void {
                    if ($record === null) {
                        $set('slug', str($state)->slug()->toString());
                    }
                }),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->helperText('Used to reference this knowledge base in code and the chat UI.'),
            Textarea::make('description')
                ->rows(3)
                ->maxLength(1000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->badge()->color('gray')->searchable(),
                TextColumn::make('documents_count')->counts('documents')->label('Documents'),
                TextColumn::make('chunks_count')->counts('chunks')->label('Chunks'),
                TextColumn::make('created_at')->dateTime()->since()->sortable()->label('Created'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'edit' => Pages\EditCollection::route('/{record}/edit'),
        ];
    }
}
