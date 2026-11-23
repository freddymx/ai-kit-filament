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
                Section::make('Chat')
                    ->description('Provider and model used for text generation.')
                    ->schema([
                        Select::make('chat_provider')
                            ->options(self::PROVIDERS)
                            ->placeholder('Use .env default')
                            ->live(),
                        TextInput::make('chat_model')
                            ->placeholder('e.g. gpt-4o-mini'),
                    ])
                    ->columns(2),

                Section::make('Embeddings')
                    ->description('Provider, model and vector size used for indexing and retrieval.')
                    ->schema([
                        Select::make('embeddings_provider')
                            ->options(self::PROVIDERS)
                            ->placeholder('Use .env default')
                            ->live(),
                        TextInput::make('embeddings_model')
                            ->placeholder('e.g. text-embedding-3-small'),
                        TextInput::make('embeddings_dimensions')
                            ->numeric(),
                    ])
                    ->columns(3),

                Section::make('Local provider')
                    ->description('Settings for the "local" sentinel (Ollama, LM Studio, llama.cpp, vLLM...).')
                    ->visible(fn (Get $get): bool => $this->isLocal($get))
                    ->schema([
                        Select::make('local_driver')
                            ->options(self::LOCAL_DRIVERS)
                            ->placeholder('Use .env default')
                            ->live(),
                        TextInput::make('local_url')
                            ->url()
                            ->required(fn (Get $get): bool => $this->isLocal($get))
                            ->placeholder('http://localhost:11434/v1'),
                        TextInput::make('local_key')
                            ->password()
                            ->revealable()
                            ->helperText('Leave blank to keep the current key'),
                        TextInput::make('local_chat_model')
                            ->placeholder('e.g. llama3.2'),
                        TextInput::make('local_embeddings_model')
                            ->placeholder('e.g. nomic-embed-text'),
                        TextInput::make('local_embeddings_dimensions')
                            ->numeric(),
                    ])
                    ->columns(2),

                Section::make('Effective value origins')
                    ->description('Values marked "database" override the matching .env value.')
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

    public function save(): void
    {
        $data = $this->form->getState();

        // effective() masks the key for display (e.g. "supe********"). Writing
        // that back would replace the real secret with the mask, and an empty
        // password field means "keep the current key" per its helper text.
        $maskedKey = app(AiProviderResolver::class)->effective()['local_key'] ?? null;
        if (array_key_exists('local_key', $data)
            && ($data['local_key'] === '' || $data['local_key'] === null || $data['local_key'] === $maskedKey)
        ) {
            unset($data['local_key']);
        }

        app(AiProviderResolver::class)->save($data);

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

        app(AiProviderResolver::class)->reset();

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

        $rows = '';
        foreach ($sources as $key => $source) {
            $value = $effective[$key] ?? null;
            $display = $value === null || $value === '' ? '—' : (string) $value;

            $rows .= '<tr>'
                . '<td class="px-2 py-1 font-mono text-xs">' . e((string) $key) . '</td>'
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
