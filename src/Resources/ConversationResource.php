<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Models\Conversation;
use SolutionForest\AiKit\Filament\Resources\ConversationResource\Pages;
use SolutionForest\AiKit\Support\UsageStats;

/**
 * Viewer for the AI SDK's conversation log (agent_conversations).
 * Read-only except for deletions; the core's Eloquent observer removes messages.
 */
final class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string | \UnitEnum | null $navigationGroup = 'AI Kit';

    protected static ?string $navigationLabel = 'Conversations';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }

    public static function canDeleteAny(): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->default('Untitled conversation')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('Messages'),
                TextColumn::make('tokens')
                    ->label('Tokens (in / out)')
                    ->badge()
                    ->color('info')
                    ->visible(fn (): bool => (bool) config('ai-kit.features.token_usage', true))
                    ->state(function (Conversation $record): string {
                        $t = app(UsageStats::class)->conversationTokens((string) $record->id);

                        return number_format($t['total']) . '  (' . number_format($t['prompt'])
                            . ' / ' . number_format($t['completion']) . ')';
                    }),
                TextColumn::make('participant_type')
                    ->label('Participant')
                    ->toggleable(),
                TextColumn::make('participant_id')
                    ->label('User')
                    ->toggleable(),
                TextColumn::make('updated_at')->dateTime()->since()->sortable()->label('Last activity'),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete this conversation?')
                    ->modalDescription('This permanently deletes the conversation and all of its messages. This action cannot be undone.'),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'view' => Pages\ViewConversation::route('/{record}'),
        ];
    }
}
