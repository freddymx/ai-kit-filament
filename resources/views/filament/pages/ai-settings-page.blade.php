<x-filament-panels::page>
    <div class="fi-section" style="margin-bottom:1rem;padding:1rem;">
        Values saved here override <code>config/ai-kit.php</code> (the <code>.env</code> defaults).
        A blank field keeps using the current value; use <strong>Reset to .env</strong> to delete all overrides.
    </div>

    {{ $this->form }}
</x-filament-panels::page>
