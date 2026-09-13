<?php

namespace App\Http\Controllers\Api\Operator;

use App\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Conversation::class);

        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $conversations = Conversation::query()
            ->orderByRaw('CASE WHEN last_message_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', (int) config('chat.pagination.per_page')));

        return ConversationResource::collection($conversations);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        $conversation->load([
            'messages' => fn ($query) => $query
                ->with('participant')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return new ConversationResource($conversation);
    }

    public function update(
        UpdateConversationRequest $request,
        Conversation $conversation,
        ConversationService $conversations,
    ): ConversationResource {
        $conversation = $conversations->updateStatus(
            $conversation,
            ConversationStatus::from((string) $request->validated('status')),
        );

        return new ConversationResource($conversation);
    }
}
