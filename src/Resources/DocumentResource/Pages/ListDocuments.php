<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources\DocumentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use SolutionForest\AiKit\Filament\Resources\DocumentResource;

final class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Upload document'),
        ];
    }
}
