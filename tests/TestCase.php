<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SolutionForest\AiKit\AiKitServiceProvider;
use SolutionForest\AiKit\Contracts\Embedder;
use SolutionForest\AiKit\Filament\AiKitFilamentServiceProvider;
use SolutionForest\AiKit\Filament\Tests\Fixtures\AdminPanelProvider;
use SolutionForest\AiKit\Filament\Tests\Support\FakeEmbedder;
use SolutionForest\AiKit\Ui\AiKitUiServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            \Laravel\Ai\AiServiceProvider::class,
            AiKitServiceProvider::class,
            AiKitUiServiceProvider::class,
            AiKitFilamentServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('ai-kit.retrieval.driver', 'sqlite');
        $app['config']->set('ai-kit.retrieval.min_score', 0.05);
        $app['config']->set('ai-kit.embeddings.dimensions', FakeEmbedder::DIMENSIONS);
        $app['config']->set('ai-kit.embeddings.cache', false);

        $app->singleton(Embedder::class, fn (): Embedder => new FakeEmbedder());
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../core/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../vendor/laravel/ai/database/migrations');
    }
}
