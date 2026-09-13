<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use App\ParticipantType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class ConversationAccessService
{
    public function authenticateGuest(Conversation|string $conversation, ?string $token): ?Participant
    {
        if ($token === null || $token === '') {
            return null;
        }

        $conversationId = $conversation instanceof Conversation ? $conversation->getKey() : $conversation;
        $tokenHash = hash('sha256', $token);

        $participant = Participant::query()
            ->where('conversation_id', $conversationId)
            ->where('type', ParticipantType::Guest)
            ->where('token_hash', $tokenHash)
            ->first();

        if ($participant === null || ! hash_equals((string) $participant->token_hash, $tokenHash)) {
            return null;
        }

        return $participant;
    }

    public function canAccessConversation(Authenticatable $principal, string $conversationId): bool
    {
        if ($principal instanceof Participant) {
            return hash_equals((string) $principal->conversation_id, $conversationId);
        }

        if (! $principal instanceof User) {
            return false;
        }

        $conversation = Conversation::query()->find($conversationId);

        return $conversation !== null && Gate::forUser($principal)->allows('view', $conversation);
    }
}
