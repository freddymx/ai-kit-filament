<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources\CollectionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use SolutionForest\AiKit\Filament\Resources\CollectionResource;

final class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;
}
