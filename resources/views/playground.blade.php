<x-filament-panels::page>
    <div class="ck-playground" style="display:flex;flex-direction:column;gap:1rem;">
        <div style="height:70vh;min-height:420px;">
            @if ($collection)
                <livewire:ai-kit.chat :collection="$collection" :collection-selector="true" :history="true" wire:key="pg-{{ $collection }}" />
            @else
                <div class="fi-section" style="padding:2rem;text-align:center;color:#6b7280;">
                    Create a collection and upload documents — or run
                    <code>php artisan ai-kit:demo</code> — to start chatting.
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
