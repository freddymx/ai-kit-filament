<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use SolutionForest\AiKit\Support\UsageStats;

/**
 * Stacked bar chart of prompt vs. completion tokens per day (last 14 days).
 */
final class TokenUsageChart extends ChartWidget
{
    protected ?string $heading = 'Token usage (last 14 days)';

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $daily = app(UsageStats::class)->daily(14);

        return [
            'datasets' => [
                [
                    'label' => 'Prompt tokens',
                    'data' => array_map(fn (array $d): int => $d['prompt'], $daily),
                    'backgroundColor' => 'rgba(96, 165, 250, 0.7)',
                    'stack' => 'tokens',
                ],
                [
                    'label' => 'Completion tokens',
                    'data' => array_map(fn (array $d): int => $d['completion'], $daily),
                    'backgroundColor' => 'rgba(37, 99, 235, 0.9)',
                    'stack' => 'tokens',
                ],
            ],
            'labels' => array_map(fn (array $d): string => substr($d['date'], 5), $daily),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true],
            ],
        ];
    }
}
