<?php

namespace App\Services;

use App\ConversationStatus;
use App\Models\Conversation;
use App\Models\Participant;
use App\ParticipantType;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * @return array{conversation: Conversation, token: string}
     */
    public function createForGuest(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $conversation = DB::transaction(function () use ($token): Conversation {
            $conversation = Conversation::query()->create([
                'status' => ConversationStatus::Open,
            ]);

            Participant::query()->create([
                'conversation_id' => $conversation->id,
                'type' => ParticipantType::Guest,
                'name' => null,
                'token_hash' => hash('sha256', $token),
            ]);

            return $conversation;
        });

        return ['conversation' => $conversation, 'token' => $token];
    }

    public function updateStatus(Conversation $conversation, ConversationStatus $status): Conversation
    {
        return DB::transaction(function () use ($conversation, $status): Conversation {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $lockedConversation->update(['status' => $status]);

            return $lockedConversation;
        });
    }
}
