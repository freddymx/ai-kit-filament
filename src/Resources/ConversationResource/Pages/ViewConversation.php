<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources\ConversationResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use SolutionForest\AiKit\Filament\Resources\ConversationResource;

final class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected string $view = 'ai-kit-filament::conversation';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'messages' => $this->getRecord()->messages()->orderBy('created_at')->get(),
            'tokens' => app(\SolutionForest\AiKit\Support\UsageStats::class)
                ->conversationTokens((string) $this->getRecord()->id),
        ];
    }
}
