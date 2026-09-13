<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use App\Services\ConversationAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateConversationGuest
{
    public function __construct(private ConversationAccessService $access) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $conversation = $request->route('conversation');

        if (! $conversation instanceof Conversation) {
            abort(404);
        }

        $participant = $this->access->authenticateGuest($conversation, $request->bearerToken());

        if ($participant === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('conversation_participant', $participant);

        return $next($request);
    }
}
