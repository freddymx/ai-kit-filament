<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use SolutionForest\AiKit\Facades\AiKit;
use SolutionForest\AiKit\Filament\Pages\AiSettingsPage;
use SolutionForest\AiKit\Support\AiSettings;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('saves settings for the active tenant', function (): void {
    config()->set('ai-kit.tenancy.enabled', true);
    AiKit::setTenant('acme');

    Livewire::test(AiSettingsPage::class)
        ->fillForm([
            'chat_provider' => 'openrouter',
            'chat_model' => 'acme-model',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AiSettings::current()?->tenant_id)->toBe('acme')
        ->and(AiSettings::current()?->chat_model)->toBe('acme-model')
        ->and(DB::table('ai_kit_settings')->count())->toBe(1);
});

it('warns and does not save or reset when tenancy is enabled but no tenant is set', function (): void {
    config()->set('ai-kit.tenancy.enabled', true);

    Livewire::test(AiSettingsPage::class)
        ->assertNotified('Set an AI Kit tenant before editing')
        ->fillForm([
            'chat_provider' => 'openrouter',
            'chat_model' => 'must-not-leak',
        ])
        ->call('save')
        ->assertNotified('AI settings not saved');

    expect(DB::table('ai_kit_settings')->count())->toBe(0);

    Livewire::test(AiSettingsPage::class)
        ->call('reset')
        ->assertNotified('AI settings not reset');

    expect(DB::table('ai_kit_settings')->count())->toBe(0);
});
