<x-filament-panels::page>
    <div class="ck-playground" style="display:flex;flex-direction:column;gap:1rem;">
        <div style="max-width:22rem;">
            <label for="ck-collection" style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.35rem;">
                Knowledge base
            </label>
            <select id="ck-collection" wire:model.live="collection"
                    class="fi-select-input block w-full rounded-lg border-none bg-white py-2 px-3 text-sm shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                @forelse ($this->collectionOptions() as $slug => $name)
                    <option value="{{ $slug }}">{{ $name }}</option>
                @empty
                    <option value="">No collections yet</option>
                @endforelse
            </select>
        </div>

        <div style="height:70vh;min-height:420px;">
            @if ($collection)
                <livewire:ai-kit.chat :collection="$collection" :history="true" wire:key="pg-{{ $collection }}" />
            @else
                <div class="fi-section" style="padding:2rem;text-align:center;color:#6b7280;">
                    Create a collection and upload documents — or run
                    <code>php artisan ai-kit:demo</code> — to start chatting.
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
