<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationContactController extends Controller
{
    public function __invoke(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate(['email' => ['present', 'nullable', 'email:rfc', 'max:254']]);
        $conversation->update(['contact_email' => $data['email']]);

        return response()->json(['data' => ['contact_email' => $conversation->contact_email]]);
    }
}
