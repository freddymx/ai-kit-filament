<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use SolutionForest\AiKit\Filament\Support\LocalModelDiscovery;
use SolutionForest\AiKit\Support\AiProviderResolver;
use SolutionForest\AiKit\Support\AiSettings;

/**
 * Edit the provider overrides used by the core AiProviderResolver.
 *
 * Every field maps 1:1 to a nullable column in `ai_kit_settings`: a non-null
 * value wins over config/ai-kit.php (.env), a null value falls back to it.
 * Resetting deletes the row, so the whole app goes back to .env defaults.
 */
final class AiSettingsPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string | \UnitEnum | null $navigationGroup = 'AI Kit';

    protected static ?string $navigationLabel = 'AI Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'ai-settings';

    protected static ?string $title = 'AI Settings';

    protected string $view = 'ai-kit-filament::filament.pages.ai-settings-page';

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    /**
     * Models reported by the local server, keyed and valued by model id.
     * Empty until discovery succeeds; the form then falls back to free text.
     *
     * @var array<string, string>
     */
    public array $localModels = [];

    public ?string $localModelsError = null;

    /** @var array<string, string> */
    private const PROVIDERS = [
        'local' => 'Local (OpenAI-compatible)',
        'anthropic' => 'Anthropic',
        'openai' => 'OpenAI',
        'openrouter' => 'OpenRouter',
        'gemini' => 'Gemini',
        'groq' => 'Groq',
        'mistral' => 'Mistral',
        'deepseek' => 'DeepSeek',
        'xai' => 'xAI',
    ];

    /** @var array<string, string> */
    private const LOCAL_DRIVERS = [
        'openai-compatible' => 'OpenAI-compatible',
        'ollama' => 'Ollama',
    ];

    public function mount(): void
    {
        $this->fillFromResolver();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([
                Section::make('Providers')
                    ->description('Choose where each part of the RAG pipeline runs. "Local" uses the shared server configured below.')
                    ->schema([
                        Select::make('chat_provider')
                            ->label('Chat provider')
                            ->options(self::PROVIDERS)
                            ->placeholder('Use .env default')
                            ->live()
                            ->afterStateUpdated(fn (?string $state) => $this->discoverOnLocalSelected($state)),
                        Select::make('embeddings_provider')
                            ->label('Embeddings provider')
                            ->options(self::PROVIDERS)
                            ->placeholder('Use .env default')
                            ->live()
                            ->afterStateUpdated(fn (?string $state) => $this->discoverOnLocalSelected($state)),
                    ])
                    ->columns(2),

                Section::make('Chat')
                    ->description('Which model answers the user.')
                    ->schema([
                        $this->modelField('chat_model', 'Chat model', 'e.g. gpt-4o-mini', 'chat_provider'),
                        ...$this->localModelFields('local_chat_model', 'Chat model (local)', 'e.g. Gemma-4-E4B-it-GGUF', 'chat_provider'),
                    ]),

                Section::make('Embeddings')
                    ->description('Which model indexes documents and embeds queries.')
                    ->schema([
                        $this->modelField('embeddings_model', 'Embedding model', 'e.g. text-embedding-3-small', 'embeddings_provider'),
                        ...$this->localModelFields('local_embeddings_model', 'Embedding model (local)', 'e.g. Qwen3-Embedding-4B-GGUF', 'embeddings_provider'),
                        TextInput::make('embeddings_dimensions')
                            ->label('Vector dimensions')
                            ->numeric()
                            ->visible(fn (Get $get): bool => $get('embeddings_provider') !== 'local')
                            ->helperText('Must match the cloud embedding model.'),
                        TextInput::make('local_embeddings_dimensions')
                            ->label('Vector dimensions (local)')
                            ->numeric()
                            ->visible(fn (Get $get): bool => $get('embeddings_provider') === 'local')
                            ->helperText('Must match the local embedding model.'),
                    ])
                    ->columns(2),

                Section::make('Local server')
                    ->description('One OpenAI-compatible endpoint shared by chat and embeddings — Ollama, LM Studio, vLLM, llama.cpp.')
                    ->visible(fn (Get $get): bool => $this->isLocal($get))
                    ->schema([
                        Select::make('local_driver')
                            ->label('Driver')
                            ->options(self::LOCAL_DRIVERS)
                            ->placeholder('Use .env default')
                            ->live()
                            ->afterStateUpdated(fn () => $this->discoverModels()),
                        TextInput::make('local_url')
                            ->label('Base URL')
                            ->url()
                            ->required(fn (Get $get): bool => $this->isLocal($get))
                            ->placeholder('http://localhost:11434/v1')
                            ->helperText('Ollama OpenAI-compatible endpoint: http://host:11434/v1. The available models are fetched when you leave this field.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->discoverModels())
                            ->suffixAction(
                                Action::make('refreshModels')
                                    ->icon('heroicon-o-arrow-path')
                                    ->tooltip('Reload models from the server')
                                    ->action(fn () => $this->discoverModels(notify: true)),
                            ),
                        TextInput::make('local_key')
                            ->label('API key (optional)')
                            ->password()
                            ->revealable()
                            ->helperText('Leave blank to keep the current key — most local servers need none.'),
                    ])
                    ->columns(2),

                Section::make('Effective configuration')
                    ->description('The value in effect for each setting, and whether it comes from this page (database) or .env.')
                    ->collapsed()
                    ->schema([
                        Html::make(fn (): HtmlString => $this->originsHtml()),
                    ]),
            ])
                ->id('save')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Save')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                        Action::make('reset')
                            ->label('Reset to .env')
                            ->color('gray')
                            ->action('reset')
                            ->requiresConfirmation()
                            ->modalHeading('Reset AI settings to .env?')
                            ->modalDescription('This deletes the saved overrides. Every provider and model setting will come from .env again.')
                            ->modalSubmitActionLabel('Reset'),
                    ]),
                ]),
        ])->statePath('data');
    }

    /**
     * The cloud and local variants of a model field live in the same section;
     * only the one matching the selected provider is rendered, so the form
     * never shows two fields that look like the same setting.
     */
    private function modelField(
        string $name,
        string $label,
        string $placeholder,
        string $providerKey,
    ): TextInput {
        return TextInput::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->visible(fn (Get $get): bool => $get($providerKey) !== 'local');
    }

    /**
     * Local model picker. When the server listed its models a Select is shown;
     * otherwise a plain TextInput (`{name}_manual`) lets the user type the id.
     * Only one of the two is ever visible, and hidden fields are not
     * dehydrated, so they never overwrite each other. save() maps the manual
     * field back onto `{name}`.
     *
     * @return array{0: Select, 1: TextInput}
     */
    private function localModelFields(
        string $name,
        string $label,
        string $placeholder,
        string $providerKey,
    ): array {
        return [
            Select::make($name)
                ->label($label)
                ->options(function (Get $get) use ($name): array {
                    $current = $get($name);

                    return filled($current) && ! array_key_exists($current, $this->localModels)
                        ? [$current => $current] + $this->localModels
                        : $this->localModels;
                })
                ->searchable()
                ->placeholder('Use .env default')
                ->dehydratedWhenHidden(false)
                ->visible(fn (Get $get): bool => $get($providerKey) === 'local' && $this->localModels !== [])
                ->required(fn (Get $get): bool => $get($providerKey) === 'local'),
            TextInput::make($name . '_manual')
                ->label($label)
                ->placeholder($placeholder)
                ->helperText(fn (): ?string => $this->localModelsError !== null
                    ? "Could not list the server's models ({$this->localModelsError}). Type the model id."
                    : null)
                ->dehydratedWhenHidden(false)
                ->visible(fn (Get $get): bool => $get($providerKey) === 'local'
                    && ($this->localModels === [] || $this->localModelsError !== null))
                ->required(fn (Get $get): bool => $get($providerKey) === 'local'),
        ];
    }

    /**
     * Fetch the model catalogue from the server currently typed in the form.
     * Never throws: on failure the form shows manual model-id inputs instead.
     */
    public function discoverModels(bool $notify = false): void
    {
        $state = $this->form->getRawState();
        $url = trim((string) ($state['local_url'] ?? ''));

        if ($url === '') {
            $this->localModels = [];
            $this->localModelsError = null;

            return;
        }

        $driver = filled($state['local_driver'] ?? null) ? (string) $state['local_driver'] : 'openai-compatible';

        $result = app(LocalModelDiscovery::class)->fetch($url, $this->discoveryKey($state['local_key'] ?? null), $driver);

        $this->localModels = $result['models'];
        $this->localModelsError = $result['error'];

        if ($result['error'] !== null) {
            Notification::make()
                ->warning()
                ->title('Could not list the server\'s models')
                ->body($result['error'] . '. You can type the model id manually.')
                ->send();
        } elseif ($notify) {
            Notification::make()->success()->title(count($result['models']) . ' models found')->send();
        }
    }

    private function discoverOnLocalSelected(?string $provider): void
    {
        if ($provider === 'local' && $this->localModels === []) {
            $this->discoverModels();
        }
    }

    /**
     * The password field holds either the user's new key, nothing, or the
     * display mask of the stored key — never send the mask as a token.
     */
    private function discoveryKey(mixed $typed): ?string
    {
        $maskedKey = app(AiProviderResolver::class)->effective()['local_key'] ?? null;

        if (filled($typed) && $typed !== $maskedKey) {
            return (string) $typed;
        }

        return AiSettings::current()?->local_key;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $resolver = app(AiProviderResolver::class);

        // effective() masks the key for display (e.g. "supe********"). Writing
        // that back would replace the real secret with the mask, and an empty
        // password field means "keep the current key" per its helper text.
        $maskedKey = $resolver->effective()['local_key'] ?? null;
        if (array_key_exists('local_key', $data)
            && ($data['local_key'] === '' || $data['local_key'] === null || $data['local_key'] === $maskedKey)
        ) {
            unset($data['local_key']);
        }

        // Without a catalogue the model id is typed into `*_manual`; map it onto
        // the real column before the resolver (which ignores unknown keys).
        foreach (['local_chat_model', 'local_embeddings_model'] as $field) {
            $manual = $data[$field . '_manual'] ?? null;

            if (is_string($manual) && trim($manual) !== '') {
                $data[$field] = trim($manual);
            }

            unset($data[$field . '_manual']);
        }

        $resolver->save($data);

        // DB is the source of truth; re-publish it into config() so the change
        // is visible in this very request, without a reload or a cache clear.
        $resolver->applyToConfig();

        $this->fillFromResolver();

        Notification::make()->success()->title('AI settings saved')->send();
    }

    /**
     * Livewire declares `reset(...$properties)` on every component, so the
     * signature has to stay compatible. Called with no arguments (our action)
     * it resets the settings row; with arguments it keeps Livewire's behaviour.
     */
    public function reset(...$properties): void
    {
        if ($properties !== []) {
            parent::reset(...$properties);

            return;
        }

        $resolver = app(AiProviderResolver::class);
        $resolver->reset();
        $resolver->applyToConfig();

        $this->fillFromResolver();

        Notification::make()->success()->title('AI settings reset to .env')->send();
    }

    private function isLocal(Get $get): bool
    {
        return $get('chat_provider') === 'local' || $get('embeddings_provider') === 'local';
    }

    private function fillFromResolver(): void
    {
        $effective = app(AiProviderResolver::class)->effective();

        // Mirror the saved ids into the manual inputs so they stay visible
        // when no model catalogue has been loaded.
        $effective['local_chat_model_manual'] = $effective['local_chat_model'] ?? null;
        $effective['local_embeddings_model_manual'] = $effective['local_embeddings_model'] ?? null;

        $this->form->fill($effective);
    }

    private function originsHtml(): HtmlString
    {
        $effective = app(AiProviderResolver::class)->effective();
        $sources = $effective['sources'] ?? [];

        $labels = [
            'chat_provider' => 'Chat provider',
            'chat_model' => 'Chat model',
            'embeddings_provider' => 'Embeddings provider',
            'embeddings_model' => 'Embedding model',
            'embeddings_dimensions' => 'Vector dimensions',
            'local_driver' => 'Local driver',
            'local_url' => 'Local base URL',
            'local_key' => 'Local API key',
            'local_chat_model' => 'Chat model (local)',
            'local_embeddings_model' => 'Embedding model (local)',
            'local_embeddings_dimensions' => 'Vector dimensions (local)',
        ];

        $rows = '';
        foreach ($sources as $key => $source) {
            $value = $effective[$key] ?? null;
            $display = $value === null || $value === '' ? '—' : (string) $value;

            $rows .= '<tr>'
                . '<td class="px-2 py-1">' . e($labels[$key] ?? (string) $key) . '</td>'
                . '<td class="px-2 py-1"><span class="fi-badge fi-size-sm ' . ($source === 'database' ? 'fi-color-primary' : 'fi-color-gray') . '">' . e((string) $source) . '</span></td>'
                . '<td class="px-2 py-1 font-mono text-xs break-all">' . e($display) . '</td>'
                . '</tr>';
        }

        $overall = $effective['source'] ?? 'env';

        return new HtmlString(
            '<p class="mb-2 text-sm">Overall source: <strong>' . e((string) $overall) . '</strong></p>'
            . '<div class="overflow-x-auto text-sm">'
            . '<table class="w-full text-left">'
            . '<thead><tr class="border-b border-gray-200 dark:border-white/10">'
            . '<th class="px-2 py-1">Setting</th><th class="px-2 py-1">Source</th><th class="px-2 py-1">Effective value</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>'
        );
    }
}
