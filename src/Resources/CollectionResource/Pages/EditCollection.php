<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources\CollectionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use SolutionForest\AiKit\Filament\Resources\CollectionResource;

final class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
