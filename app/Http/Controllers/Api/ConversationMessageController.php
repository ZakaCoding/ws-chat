<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Participant;
use App\Services\MessageService;

class ConversationMessageController extends Controller
{
    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
        MessageService $messages,
    ): MessageResource {
        $participant = $request->attributes->get('conversation_participant');

        abort_unless($participant instanceof Participant, 401);

        $message = $messages->createForGuest(
            $conversation,
            $participant,
            (string) $request->validated('body'),
        );

        return new MessageResource($message);
    }
}
