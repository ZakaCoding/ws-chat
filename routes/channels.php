<?php

use App\Broadcasting\ConversationChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', ConversationChannel::class, [
    'guards' => ['web', 'guest'],
]);
