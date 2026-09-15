<?php

namespace App\Services;

use App\ConversationStatus;
use App\Events\MessageCreated;
use App\Exceptions\ConversationClosedException;
use App\Jobs\DispatchMessageNotifications;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use App\Models\User;
use App\ParticipantType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function createForGuest(Conversation $conversation, Participant $participant, string $body, ?string $clientMessageId = null): Message
    {
        if (! hash_equals((string) $participant->conversation_id, (string) $conversation->id)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($conversation, $participant, $body, $clientMessageId): Message {
            $lockedConversation = $this->lockOpenConversation($conversation);

            return $this->storeMessage($lockedConversation, $participant, $body, $clientMessageId);
        });
    }

    public function createForOperator(Conversation $conversation, User $operator, string $body, ?string $clientMessageId = null): Message
    {
        return DB::transaction(function () use ($conversation, $operator, $body, $clientMessageId): Message {
            $lockedConversation = $this->lockOpenConversation($conversation);
            $participant = Participant::query()->firstOrCreate(
                [
                    'conversation_id' => $lockedConversation->id,
                    'user_id' => $operator->id,
                ],
                [
                    'type' => ParticipantType::Operator,
                    'name' => $operator->name,
                ],
            );

            return $this->storeMessage($lockedConversation, $participant, $body, $clientMessageId);
        });
    }

    private function lockOpenConversation(Conversation $conversation): Conversation
    {
        $lockedConversation = Conversation::query()
            ->lockForUpdate()
            ->findOrFail($conversation->id);

        if ($lockedConversation->status !== ConversationStatus::Open) {
            throw new ConversationClosedException;
        }

        return $lockedConversation;
    }

    private function storeMessage(Conversation $conversation, Participant $participant, string $body, ?string $clientMessageId = null): Message
    {
        if ($clientMessageId !== null) {
            $existing = $participant->messages()->where('client_message_id', $clientMessageId)->first();
            if ($existing !== null) {
                abort_unless($existing->body === $body, 409, 'This message identifier was already used for different text.');

                return $existing->load('participant');
            }
        }
        $message = $conversation->messages()->create([
            'participant_id' => $participant->id,
            'body' => $body,
            'client_message_id' => $clientMessageId,
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);
        $message->setRelation('participant', $participant);

        MessageCreated::dispatch($message);
        if (config('push.enabled')) {
            DispatchMessageNotifications::dispatch($message->id)->afterCommit();
        }

        return $message;
    }
}
