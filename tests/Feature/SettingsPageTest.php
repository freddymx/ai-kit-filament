<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Providers\Provider;
use Livewire\Livewire;
use SolutionForest\AiKit\Filament\AiKitPlugin;
use SolutionForest\AiKit\Filament\Pages\AiSettingsPage;
use SolutionForest\AiKit\Support\AiProviderResolver;
use SolutionForest\AiKit\Support\AiSettings;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('registers the settings page when features.settings is enabled', function (): void {
    expect(Filament::getPanel('admin')->getPages())->toContain(AiSettingsPage::class)
        ->and(AiSettingsPage::getSlug())->toBe('ai-settings')
        ->and(AiSettingsPage::getNavigationGroup())->toBe('AI Kit')
        ->and(AiSettingsPage::getNavigationSort())->toBe(5);
});

it('does not register the settings page when features.settings is disabled', function (): void {
    config()->set('ai-kit.features.settings', false);

    // Panels register their plugin at boot, so exercise register() on a fresh
    // panel with the flag flipped instead of mutating the already-booted one.
    $panel = Panel::make()->id('settings-disabled');
    AiKitPlugin::make()->register($panel);

    expect($panel->getPages())->not->toContain(AiSettingsPage::class);
});

it('mounts and renders the settings page with the effective values', function (): void {
    $effective = app(AiProviderResolver::class)->effective();

    $page = Livewire::test(AiSettingsPage::class)
        ->assertOk()
        ->assertSet('data.chat_provider', $effective['chat_provider'])
        ->assertSet('data.chat_model', $effective['chat_model'])
        ->assertSee('Effective configuration')
        ->assertSee('Reset to .env')
        ->assertSeeHtml('wire:submit="save"')
        ->assertSeeHtml('id="save"');

    // The actions live in the form footer, not directly on the page.
    $footer = $page->instance()->form->getComponents()[0]
        ->getChildSchema('footer')->getComponents()[0];
    $resetAction = $footer->getChildSchema()->getComponents()[1];

    expect($resetAction->getName())->toBe('reset')
        ->and($resetAction->isConfirmationRequired())->toBeTrue();

    $this->get('/admin/ai-settings')->assertOk();
});

it('saves provider overrides in the database and the resolver sees them', function (): void {
    Livewire::test(AiSettingsPage::class)
        ->fillForm([
            'chat_provider' => 'local',
            'chat_model' => 'custom-chat',
            'local_url' => 'http://127.0.0.1:8080/v1',
            'local_chat_model_manual' => 'local-llm',
            'embeddings_dimensions' => '768',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = AiSettings::current();

    expect($settings)->not->toBeNull()
        ->and($settings->chat_provider)->toBe('local')
        ->and($settings->chat_model)->toBeNull()
        ->and($settings->local_chat_model)->toBe('local-llm')
        ->and($settings->local_url)->toBe('http://127.0.0.1:8080/v1')
        ->and($settings->embeddings_dimensions)->toBe(768);

    $effective = app(AiProviderResolver::class)->effective();

    expect($effective['chat_provider'])->toBe('local')
        ->and($effective['chat_model'])->toBe(config('ai-kit.chat.model'))
        ->and($effective['local_chat_model'])->toBe('local-llm')
        ->and($effective['embeddings_dimensions'])->toBe(768)
        ->and($effective['sources']['chat_provider'])->toBe('database')
        ->and($effective['source'])->toBe('database');
});

it('does not overwrite the real key when the masked value is submitted', function (): void {
    $secret = 'super-secret-key-123';

    app(AiProviderResolver::class)->save(['local_key' => $secret]);

    $mask = app(AiProviderResolver::class)->effective()['local_key'];
    expect($mask)->toBe('supe********');

    Livewire::test(AiSettingsPage::class)
        ->set('data.local_key', $mask)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AiSettings::current()->local_key)->toBe($secret)
        ->and(AiSettings::current()->local_key)->not->toBe($mask);
});

it('deletes the settings row on reset so values come from .env again', function (): void {
    app(AiProviderResolver::class)->save([
        'chat_provider' => 'local',
        'chat_model' => 'overridden',
    ]);

    expect(AiSettings::current())->not->toBeNull();

    Livewire::test(AiSettingsPage::class)
        ->call('reset')
        ->assertHasNoFormErrors();

    $effective = app(AiProviderResolver::class)->effective();

    expect(AiSettings::current())->toBeNull()
        ->and($effective['chat_provider'])->toBe(config('ai-kit.chat.provider'))
        ->and($effective['source'])->toBe('env')
        ->and($effective['sources']['chat_provider'])->toBe('env');
});

it('resolves the supplied local server and models end to end', function (): void {
    Livewire::test(AiSettingsPage::class)
        ->fillForm([
            'chat_provider' => 'local',
            'embeddings_provider' => 'local',
            'local_driver' => 'openai-compatible',
            'local_url' => 'http://100.103.128.28/v1',
            'local_chat_model_manual' => 'Gemma-4-E4B-it-GGUF',
            'local_embeddings_model_manual' => 'Qwen3-Embedding-4B-GGUF',
            'local_embeddings_dimensions' => '768',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = AiSettings::current();

    expect($settings->local_url)->toBe('http://100.103.128.28/v1')
        ->and($settings->local_chat_model)->toBe('Gemma-4-E4B-it-GGUF')
        ->and($settings->local_embeddings_model)->toBe('Qwen3-Embedding-4B-GGUF');

    $resolver = app(AiProviderResolver::class);
    $chat = $resolver->chatProvider();
    $embeddings = $resolver->embeddingsProvider();

    expect($chat)->toBeArray()
        ->and($chat[0])->toBeInstanceOf(Provider::class)
        ->and($chat[0]->name())->toBe(AiProviderResolver::LOCAL_PROVIDER_NAME)
        ->and($chat[0]->additionalConfiguration()['url'])->toBe('http://100.103.128.28/v1')
        ->and($embeddings)->toBeArray()
        ->and($embeddings[0]->additionalConfiguration()['url'])->toBe('http://100.103.128.28/v1')
        ->and($resolver->chatModel())->toBe('Gemma-4-E4B-it-GGUF')
        ->and($resolver->embeddingsModel())->toBe('Qwen3-Embedding-4B-GGUF')
        ->and($resolver->embeddingsDimensions())->toBe(768);

    // Saving applies the row to config() in the same request, so direct SDK
    // calls and code reading config('ai-kit.*') see the local server too.
    expect(config('ai-kit.chat.provider'))->toBe('local')
        ->and(config('ai-kit.embeddings.provider'))->toBe('local')
        ->and(config('ai.providers.local.url'))->toBe('http://100.103.128.28/v1')
        ->and(config('ai.providers.local.models.text.default'))->toBe('Gemma-4-E4B-it-GGUF')
        ->and(config('ai.providers.local.models.embeddings.default'))->toBe('Qwen3-Embedding-4B-GGUF')
        ->and(config('ai.providers.local.models.embeddings.dimensions'))->toBe(768)
        ->and(config('ai.default'))->toBe('local')
        ->and(config('ai.default_for_embeddings'))->toBe('local');
});

it('returns config to the .env values on reset', function (): void {
    // At boot there is no DB row, so these are the .env-backed values.
    $env = [
        'chat_provider' => config('ai-kit.chat.provider'),
        'chat_model' => config('ai-kit.chat.model'),
        'embeddings_dimensions' => config('ai-kit.embeddings.dimensions'),
        'local_url' => config('ai.providers.local.url'),
        'default' => config('ai.default'),
        'default_for_embeddings' => config('ai.default_for_embeddings'),
    ];

    Livewire::test(AiSettingsPage::class)
        ->fillForm([
            'chat_provider' => 'local',
            'embeddings_provider' => 'local',
            'local_url' => 'http://100.103.128.28/v1',
            'local_chat_model_manual' => 'Gemma-4-E4B-it-GGUF',
            'local_embeddings_model_manual' => 'Qwen3-Embedding-4B-GGUF',
            'local_embeddings_dimensions' => '768',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('ai-kit.chat.provider'))->toBe('local')
        ->and(config('ai.providers.local.url'))->toBe('http://100.103.128.28/v1');

    Livewire::test(AiSettingsPage::class)
        ->call('reset')
        ->assertHasNoFormErrors();

    expect(config('ai-kit.chat.provider'))->toBe($env['chat_provider'])
        ->and(config('ai-kit.chat.model'))->toBe($env['chat_model'])
        ->and(config('ai-kit.embeddings.dimensions'))->toBe($env['embeddings_dimensions'])
        ->and(config('ai.providers.local.url'))->toBe($env['local_url'])
        ->and(config('ai.default'))->toBe($env['default'])
        ->and(config('ai.default_for_embeddings'))->toBe($env['default_for_embeddings']);
});


it('discovers the models of an OpenAI-compatible server when the URL is set', function (): void {
    Http::fake([
        '*' => Http::response(['data' => [['id' => 'qwen3:8b'], ['id' => 'llama3.2']]]),
    ]);

    Livewire::test(AiSettingsPage::class)
        ->set('data.chat_provider', 'local')
        ->set('data.local_driver', 'openai-compatible')
        ->set('data.local_url', 'http://localhost:11434')
        ->assertSet('localModels', ['llama3.2' => 'llama3.2', 'qwen3:8b' => 'qwen3:8b'])
        ->assertSet('localModelsError', null)
        ->assertFormFieldVisible('local_chat_model')
        ->assertFormFieldHidden('local_chat_model_manual');

    Http::assertSent(fn ($request): bool => $request->url() === 'http://localhost:11434/v1/models');
});

it('uses the Ollama tags endpoint for the ollama driver', function (): void {
    Http::fake([
        '*' => Http::response(['models' => [['name' => 'llama3.2']]]),
    ]);

    Livewire::test(AiSettingsPage::class)
        ->set('data.chat_provider', 'local')
        ->set('data.local_driver', 'ollama')
        ->set('data.local_url', 'http://localhost:11434')
        ->assertSet('localModels', ['llama3.2' => 'llama3.2'])
        ->assertSet('localModelsError', null);

    Http::assertSent(fn ($request): bool => $request->url() === 'http://localhost:11434/api/tags');
});

it('falls back to a manual model input when the models cannot be listed', function (): void {
    Http::fake([
        '*' => Http::response('boom', 500),
    ]);

    Livewire::test(AiSettingsPage::class)
        ->set('data.chat_provider', 'local')
        ->set('data.local_url', 'http://localhost:11434')
        ->assertSet('localModels', [])
        ->assertSet('localModelsError', 'HTTP 500')
        ->assertFormFieldHidden('local_chat_model')
        ->assertFormFieldVisible('local_chat_model_manual');
});

it('falls back to a manual model input when the server is unreachable', function (): void {
    Http::fake(fn () => throw new ConnectionException('refused'));

    Livewire::test(AiSettingsPage::class)
        ->set('data.chat_provider', 'local')
        ->set('data.local_url', 'http://localhost:11434')
        ->assertSet('localModels', [])
        ->assertNotSet('localModelsError', null)
        ->assertFormFieldVisible('local_chat_model_manual');
});

it('saves the model picked from the discovered catalogue', function (): void {
    Http::fake([
        '*' => Http::response(['data' => [['id' => 'qwen3:8b'], ['id' => 'llama3.2']]]),
    ]);

    Livewire::test(AiSettingsPage::class)
        ->set('data.chat_provider', 'local')
        ->set('data.local_url', 'http://localhost:11434')
        ->set('data.local_chat_model', 'qwen3:8b')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AiSettings::current()->local_chat_model)->toBe('qwen3:8b');
});

it('saves a manually typed model id', function (): void {
    Livewire::test(AiSettingsPage::class)
        ->fillForm([
            'chat_provider' => 'local',
            'local_url' => 'http://127.0.0.1:8080/v1',
            'local_chat_model_manual' => 'mi-modelo',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AiSettings::current()->local_chat_model)->toBe('mi-modelo');
});

it('requires a model id when the provider is local', function (): void {
    Livewire::test(AiSettingsPage::class)
        ->fillForm([
            'chat_provider' => 'local',
            'local_url' => 'http://127.0.0.1:8080/v1',
            'local_chat_model_manual' => '',
        ])
        ->call('save')
        ->assertHasFormErrors(['local_chat_model_manual' => 'required']);
});
