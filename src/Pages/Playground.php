<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Pages;

use Filament\Pages\Page;
use SolutionForest\AiKit\Models\Collection;

/**
 * Client-demo page: pick a knowledge base and chat against it live. Backed by
 * the same Livewire chat component consumers embed in their own apps.
 */
final class Playground extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-play-circle';

    protected static string | \UnitEnum | null $navigationGroup = 'AI Kit';

    protected static ?int $navigationSort = 4;

    protected string $view = 'ai-kit-filament::playground';

    /** The selected collection slug. */
    public ?string $collection = null;

    public function mount(): void
    {
        $this->collection = $this->collectionOptions()->keys()->first()
            ?? config('ai-kit.default_collection', 'default');
    }

    /**
     * @return \Illuminate\Support\Collection<string, string>
     */
    public function collectionOptions(): \Illuminate\Support\Collection
    {
        return Collection::query()
            ->orderBy('name')
            ->pluck('name', 'slug');
    }

    public static function getNavigationLabel(): string
    {
        return 'Playground';
    }
}
