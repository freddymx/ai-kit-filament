<x-filament-panels::page>
    @php $showTokens = (bool) config('ai-kit.features.token_usage', true); @endphp

    @if ($showTokens)
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:.5rem;font-size:.85rem;">
            <span style="background:#eff6ff;color:#1e40af;border-radius:999px;padding:4px 12px;font-weight:600;">
                Total tokens: {{ number_format($tokens['total']) }}
            </span>
            <span style="color:#6b7280;padding:4px 0;">
                {{ number_format($tokens['prompt']) }} input · {{ number_format($tokens['completion']) }} output
            </span>
        </div>
    @endif

    <div style="display:flex;flex-direction:column;gap:.75rem;max-width:52rem;">
        @forelse ($messages as $message)
            @php
                $isUser = $message->role === 'user';
                $tok = \SolutionForest\AiKit\Support\UsageStats::messageTokens($message->usage);
                $hasTokens = ($tok['in'] + $tok['out']) > 0;
            @endphp
            <div style="display:flex;{{ $isUser ? 'justify-content:flex-end' : 'justify-content:flex-start' }};">
                <div style="max-width:80%;padding:.7rem 1rem;border-radius:14px;line-height:1.55;font-size:.9rem;
                            {{ $isUser
                                ? 'background:#2563eb;color:#fff;border-bottom-right-radius:4px;'
                                : 'background:#f3f4f6;color:#111827;border-bottom-left-radius:4px;' }}">
                    <div style="display:flex;justify-content:space-between;gap:1rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;opacity:.7;margin-bottom:.25rem;">
                        <span>{{ $message->role }}</span>
                        @if ($showTokens && $hasTokens)
                            <span style="text-transform:none;">↑{{ number_format($tok['in']) }} in · ↓{{ number_format($tok['out']) }} out</span>
                        @endif
                    </div>
                    <div style="white-space:pre-wrap;word-break:break-word;">{{ $message->content }}</div>
                </div>
            </div>
        @empty
            <p style="color:#6b7280;">This conversation has no messages.</p>
        @endforelse
    </div>
</x-filament-panels::page>
