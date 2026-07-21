<div style="display:flex;flex-direction:column;gap:.6rem;max-height:60vh;overflow-y:auto;">
    @forelse ($chunks as $chunk)
        <div style="border:1px solid #e5e7eb;border-radius:10px;padding:.7rem .9rem;">
            <div style="display:flex;justify-content:space-between;font-size:.72rem;color:#6b7280;margin-bottom:.35rem;">
                <span>Chunk #{{ $chunk->chunk_index }}</span>
                <span>{{ $chunk->embedding ? 'embedded' : 'pending' }}@if($chunk->embedding_model) · {{ $chunk->embedding_model }}@endif</span>
            </div>
            <div style="font-size:.85rem;line-height:1.5;white-space:pre-wrap;word-break:break-word;">{{ \Illuminate\Support\Str::limit($chunk->content, 600) }}</div>
        </div>
    @empty
        <p style="color:#6b7280;">No chunks yet — this document may still be processing.</p>
    @endforelse
</div>
