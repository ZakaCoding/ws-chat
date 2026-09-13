<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    public function store(ConversationService $conversations): JsonResponse
    {
        $result = $conversations->createForGuest();
        $conversation = $result['conversation'];

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'token' => $result['token'],
                'status' => $conversation->status->value,
                'created_at' => $conversation->created_at->toISOString(),
            ],
        ], 201);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        $conversation->load([
            'messages' => fn ($query) => $query
                ->with('participant')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return new ConversationResource($conversation);
    }
}
