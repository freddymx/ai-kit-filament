<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use SolutionForest\AiKit\Filament\Pages\AnalyticsDashboard;
use SolutionForest\AiKit\Filament\Pages\Playground;
use SolutionForest\AiKit\Filament\Resources\CollectionResource;
use SolutionForest\AiKit\Filament\Resources\ConversationResource;
use SolutionForest\AiKit\Filament\Resources\DocumentResource;

/**
 * Registers the ai-kit admin panel: knowledge-base management, document
 * ingestion, conversation logs, a demo Playground, and a stats widget.
 *
 *   use SolutionForest\AiKit\Filament\AiKitPlugin;
 *
 *   $panel->plugin(AiKitPlugin::make());
 */
final class AiKitPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'ai-kit';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                CollectionResource::class,
                DocumentResource::class,
                ConversationResource::class,
            ])
            ->pages(array_values(array_filter([
                config('ai-kit.features.dashboard', true) ? AnalyticsDashboard::class : null,
                Playground::class,
            ])));
        // Widgets are rendered on the AnalyticsDashboard page (not the panel's
        // default dashboard), so they are not registered panel-wide here.
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
