<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament;

use Illuminate\Support\ServiceProvider;

final class AiKitFilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-kit-filament');
    }
}
