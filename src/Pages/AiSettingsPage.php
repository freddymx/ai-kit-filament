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
use SolutionForest\AiKit\Support\AiProviderResolver;

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
                            ->live(),
                        Select::make('embeddings_provider')
                            ->label('Embeddings provider')
                            ->options(self::PROVIDERS)
                            ->placeholder('Use .env default')
                            ->live(),
                    ])
                    ->columns(2),

                Section::make('Chat')
                    ->description('Which model answers the user.')
                    ->schema([
                        $this->modelField('chat_model', 'Chat model', 'e.g. gpt-4o-mini', 'chat_provider', false),
                        $this->modelField('local_chat_model', 'Chat model (local)', 'e.g. Gemma-4-E4B-it-GGUF', 'chat_provider', true),
                    ]),

                Section::make('Embeddings')
                    ->description('Which model indexes documents and embeds queries.')
                    ->schema([
                        $this->modelField('embeddings_model', 'Embedding model', 'e.g. text-embedding-3-small', 'embeddings_provider', false),
                        $this->modelField('local_embeddings_model', 'Embedding model (local)', 'e.g. Qwen3-Embedding-4B-GGUF', 'embeddings_provider', true),
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
                            ->live(),
                        TextInput::make('local_url')
                            ->label('Base URL')
                            ->url()
                            ->required(fn (Get $get): bool => $this->isLocal($get))
                            ->placeholder('http://localhost:11434/v1')
                            ->helperText('Ollama OpenAI-compatible endpoint: http://host:11434/v1'),
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
        bool $local,
    ): TextInput {
        return TextInput::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->visible(fn (Get $get): bool => $local
                ? $get($providerKey) === 'local'
                : $get($providerKey) !== 'local')
            ->required(fn (Get $get): bool => $local && $get($providerKey) === 'local');
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
        $this->form->fill(app(AiProviderResolver::class)->effective());
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
