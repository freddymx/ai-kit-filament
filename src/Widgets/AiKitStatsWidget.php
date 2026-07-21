<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Ai\Models\Conversation;
use SolutionForest\AiKit\Enums\DocumentStatus;
use SolutionForest\AiKit\Models\Chunk;
use SolutionForest\AiKit\Models\Document;
use SolutionForest\AiKit\Support\UsageStats;

final class AiKitStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'AI Kit';

    protected function getStats(): array
    {
        $indexed = Document::query()->where('status', DocumentStatus::Indexed)->count();

        $conversationsThisWeek = Conversation::query()
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        $stats = [
            Stat::make('Documents indexed', (string) $indexed)
                ->description('Ready for retrieval')
                ->color('success'),
            Stat::make('Chunks', (string) Chunk::query()->count())
                ->description('Embedded passages'),
            Stat::make('Conversations this week', (string) $conversationsThisWeek)
                ->description('Across all agents'),
        ];

        if (config('ai-kit.features.token_usage', true)) {
            $usage = app(UsageStats::class)->totals(30);

            $stats[] = Stat::make('Tokens (30d)', $this->compact($usage['total']))
                ->description($this->compact($usage['prompt']) . ' in · ' . $this->compact($usage['completion']) . ' out')
                ->color('info');
            $stats[] = Stat::make('Est. cost (30d)', '$' . number_format($usage['cost'], 2))
                ->description('Based on ' . config('ai-kit.chat.model', 'model') . ' pricing')
                ->color('warning');
        }

        return $stats;
    }

    private function compact(int $n): string
    {
        return match (true) {
            $n >= 1_000_000 => round($n / 1_000_000, 2) . 'M',
            $n >= 1_000 => round($n / 1_000, 1) . 'k',
            default => (string) $n,
        };
    }
}
