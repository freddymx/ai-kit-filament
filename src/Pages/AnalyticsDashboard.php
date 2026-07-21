<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Pages;

use Filament\Pages\Page;
use SolutionForest\AiKit\Filament\Widgets\AiKitStatsWidget;
use SolutionForest\AiKit\Filament\Widgets\TokenUsageChart;

/**
 * The ai-kit analytics dashboard — a dedicated page under the AI Kit menu
 * (rather than the panel's default dashboard) showing usage stats + charts.
 */
final class AnalyticsDashboard extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | \UnitEnum | null $navigationGroup = 'AI Kit';

    protected static ?int $navigationSort = 0;

    protected string $view = 'ai-kit-filament::analytics';

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function getTitle(): string
    {
        return 'AI Kit Dashboard';
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return array_values(array_filter([
            AiKitStatsWidget::class,
            config('ai-kit.features.token_usage', true) ? TokenUsageChart::class : null,
        ]));
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 2;
    }
}
