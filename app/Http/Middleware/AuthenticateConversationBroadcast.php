<?php

namespace App\Http\Middleware;

use App\Models\Participant;
use App\Models\User;
use App\Services\ConversationAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateConversationBroadcast
{
    public function __construct(private ConversationAccessService $access) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() instanceof User) {
            return $next($request);
        }

        $channelName = $request->string('channel_name')->toString();

        if (preg_match('/\Aprivate-conversation\.([0-9A-HJKMNP-TV-Z]{26})\z/iD', $channelName, $matches) === 1) {
            $participant = $this->access->authenticateGuest(
                strtolower($matches[1]),
                $request->bearerToken(),
            );

            if ($participant instanceof Participant) {
                $request->attributes->set('conversation_participant', $participant);
            }
        }

        return $next($request);
    }
}
