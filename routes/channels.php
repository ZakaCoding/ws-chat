<?php

use App\Broadcasting\ConversationChannel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', ConversationChannel::class, [
    'guards' => ['sanctum', 'web', 'guest'],
]);

Broadcast::channel('operator.inbox', fn (User $user): bool => $user->is_operator, [
    'guards' => ['sanctum'],
]);
