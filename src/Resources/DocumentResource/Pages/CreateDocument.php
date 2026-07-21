<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Resources\DocumentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use SolutionForest\AiKit\Filament\Resources\DocumentResource;
use SolutionForest\AiKit\Ingestion\IngestionService;

final class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return DocumentResource::prepareUpload($data);
    }

    protected function afterCreate(): void
    {
        // Kick off extract → chunk → embed for the freshly uploaded document.
        app(IngestionService::class)->queue($this->record);
    }
}
