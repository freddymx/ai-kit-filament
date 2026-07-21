<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use SolutionForest\AiKit\Filament\AiKitPlugin;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(AiKitPlugin::make());
    }
}
