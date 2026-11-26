<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Pages;

use Filament\Pages\Page;
use SolutionForest\AiKit\Models\Collection;

/**
 * Client-demo page: chat against a knowledge base live. The base selector is
 * rendered by the embedded Livewire chat component (collection-selector), the
 * same one consumers embed in their own apps.
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
     * Only used by mount() to preselect the first collection: the selector is
     * rendered by the Livewire chat component, not by this page's view.
     *
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
