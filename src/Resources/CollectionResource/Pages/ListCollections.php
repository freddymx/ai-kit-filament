<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources\CollectionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use SolutionForest\AiKit\Filament\Resources\CollectionResource;

final class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
