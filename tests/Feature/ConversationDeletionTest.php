<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\Conversation;

uses(RefreshDatabase::class);

/**
 * The Filament DeleteAction calls Eloquent delete() on the model. The core
 * registers a Conversation::deleting() observer that removes the messages,
 * because the SDK migration has no ON DELETE CASCADE. We exercise that delete
 * path directly here: invoking a Livewire table action outside a rendered
 * panel component is not a reliable test surface.
 */
function seedFilamentDeletableConversation(int $messages = 2): string
{
    $id = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $id,
        'participant_type' => null,
        'participant_id' => null,
        'title' => 'Deletable conversation',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($i = 0; $i < $messages; $i++) {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $id,
            'participant_type' => null,
            'participant_id' => null,
            'agent' => 'TestAgent',
            'role' => $i % 2 === 0 ? 'user' : 'assistant',
            'content' => "Turn {$i}",
            'attachments' => '[]',
            'steps' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'status' => MessageStatus::Completed->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $id;
}

it('deletes a conversation and its messages through the core observer', function (): void {
    $id = seedFilamentDeletableConversation(messages: 3);
    $untouched = seedFilamentDeletableConversation(messages: 1);

    expect(DB::table('agent_conversation_messages')->where('conversation_id', $id)->count())->toBe(3);

    Conversation::query()->findOrFail($id)->delete();

    expect(DB::table('agent_conversations')->where('id', $id)->exists())->toBeFalse()
        ->and(DB::table('agent_conversation_messages')->where('conversation_id', $id)->count())->toBe(0)
        // The observer must not touch other conversations' messages.
        ->and(DB::table('agent_conversations')->where('id', $untouched)->exists())->toBeTrue()
        ->and(DB::table('agent_conversation_messages')->where('conversation_id', $untouched)->count())->toBe(1);
});
