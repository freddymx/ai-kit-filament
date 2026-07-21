<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources\ConversationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use SolutionForest\AiKit\Filament\Resources\ConversationResource;

final class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;
}
