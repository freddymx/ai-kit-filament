<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SolutionForest\AiKit\Filament\Pages\AnalyticsDashboard;
use SolutionForest\AiKit\Filament\Pages\Playground;
use SolutionForest\AiKit\Filament\Resources\CollectionResource;
use SolutionForest\AiKit\Filament\Resources\ConversationResource;
use SolutionForest\AiKit\Filament\Resources\DocumentResource;
use SolutionForest\AiKit\Filament\Widgets\AiKitStatsWidget;
use SolutionForest\AiKit\Filament\Widgets\TokenUsageChart;
use SolutionForest\AiKit\Ingestion\IngestionService;
use SolutionForest\AiKit\Models\Collection;
use SolutionForest\AiKit\Models\Document;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('registers ai-kit resources and pages (dashboard + playground) on the panel', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getResources())
        ->toContain(CollectionResource::class, DocumentResource::class, ConversationResource::class)
        ->and($panel->getPages())->toContain(AnalyticsDashboard::class, Playground::class);
});

it('renders the stats + token chart as header widgets on the analytics dashboard (not the default dashboard)', function (): void {
    $method = new ReflectionMethod(AnalyticsDashboard::class, 'getHeaderWidgets');
    $method->setAccessible(true);
    $widgets = $method->invoke(new AnalyticsDashboard());

    expect($widgets)->toContain(AiKitStatsWidget::class, TokenUsageChart::class)
        // widgets are NOT registered panel-wide, so they don't hit the default dashboard
        ->and(Filament::getPanel('admin')->getWidgets())->not->toContain(AiKitStatsWidget::class);
});

it('hides token stats + chart when the token_usage feature is disabled', function (): void {
    config()->set('ai-kit.features.token_usage', false);

    $widgets = (new ReflectionMethod(AnalyticsDashboard::class, 'getHeaderWidgets'));
    $widgets->setAccessible(true);
    expect($widgets->invoke(new AnalyticsDashboard()))
        ->toContain(AiKitStatsWidget::class)
        ->not->toContain(TokenUsageChart::class);

    $stats = (new ReflectionMethod(AiKitStatsWidget::class, 'getStats'));
    $stats->setAccessible(true);
    expect($stats->invoke(new AiKitStatsWidget()))->toHaveCount(3); // no tokens/cost stats
});

it('exposes a documents relation manager on the collection resource', function (): void {
    expect(CollectionResource::getRelations())
        ->toContain(\SolutionForest\AiKit\Filament\Resources\CollectionResource\RelationManagers\DocumentsRelationManager::class);
});

it('binds each resource to its model and makes conversations read-only', function (): void {
    expect(CollectionResource::getModel())->toBe(Collection::class)
        ->and(DocumentResource::getModel())->toBe(Document::class)
        ->and(ConversationResource::getModel())->toBe(\Laravel\Ai\Models\Conversation::class)
        ->and(ConversationResource::canCreate())->toBeFalse();
});

it('maps an uploaded file into ingestible document attributes', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('ai-kit/uploads/policy.md', "Refund policy body.");

    $data = DocumentResource::prepareUpload([
        'collection_id' => 1,
        'title' => '',
        'source_upload' => 'ai-kit/uploads/policy.md',
    ]);

    expect($data)->not->toHaveKey('source_upload')
        ->and($data['source_path'])->toEndWith('ai-kit/uploads/policy.md')
        ->and($data['title'])->toBe('policy')
        ->and($data['mime'])->toContain('text/');
});

it('keeps the upload field in the form data so prepareUpload can set source_path', function (): void {
    // Regression: the FileUpload was ->dehydrated(false), which stripped
    // source_upload from the form data BEFORE prepareUpload() read it — so
    // every document created through the panel got a null source_path and
    // ingestion failed with "no source file to extract". The field must stay
    // dehydrated (prepareUpload unsets it before the model is saved).
    $find = function ($schema): ?\Filament\Forms\Components\FileUpload {
        foreach ($schema->getComponents(withHidden: true) as $component) {
            if ($component instanceof \Filament\Forms\Components\FileUpload
                && $component->getName() === 'source_upload') {
                return $component;
            }
        }

        return null;
    };

    $page = new \SolutionForest\AiKit\Filament\Resources\DocumentResource\Pages\CreateDocument();
    $schema = \Filament\Schemas\Schema::make($page)->operation('create');
    $upload = $find(DocumentResource::form($schema));

    expect($upload)->not->toBeNull()
        ->and($upload->isDehydrated())->toBeTrue();
});

it('preselects the first collection when the playground mounts', function (): void {
    Collection::create(['name' => 'Beta', 'slug' => 'beta']);
    Collection::create(['name' => 'Alpha', 'slug' => 'alpha']);

    $page = app(Playground::class);
    $page->mount();

    // Ordered by name → "Alpha" first.
    expect($page->collection)->toBe('alpha')
        ->and($page->collectionOptions()->keys()->all())->toContain('alpha', 'beta');
});

it('computes dashboard stats from indexed documents and chunks', function (): void {
    $collection = Collection::create(['name' => 'KB']);
    $path = sys_get_temp_dir() . '/w_' . uniqid() . '.md';
    file_put_contents($path, str_repeat('Widget stat knowledge content. ', 20));
    app(IngestionService::class)->createFromPath($collection, $path, sync: true);

    $widget = app(AiKitStatsWidget::class);
    $method = (new Reflectionmethod($widget, 'getStats'));
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(5); // docs, chunks, conversations, tokens, cost
});
