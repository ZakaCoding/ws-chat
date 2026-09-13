<?php

namespace App\Broadcasting;

use App\Services\ConversationAccessService;
use Illuminate\Contracts\Auth\Authenticatable;

class ConversationChannel
{
    public function __construct(private ConversationAccessService $access) {}

    public function join(Authenticatable $principal, string $conversationId): bool
    {
        return $this->access->canAccessConversation($principal, $conversationId);
    }
}
