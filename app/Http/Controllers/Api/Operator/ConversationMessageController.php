<?php

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\Gate;

class ConversationMessageController extends Controller
{
    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
        MessageService $messages,
    ): MessageResource {
        Gate::authorize('update', $conversation);

        $operator = $request->user();
        abort_unless($operator instanceof User, 401);

        $message = $messages->createForOperator(
            $conversation,
            $operator,
            (string) $request->validated('body'),
        );

        return new MessageResource($message);
    }
}
