<?php

return [
    'pagination' => [
        'per_page' => (int) env('CHAT_OPERATOR_PAGE_SIZE', 25),
    ],

    'rate_limits' => [
        'conversation_creation_per_minute' => (int) env('CHAT_CONVERSATION_CREATION_RATE', 10),
        'guest_messages_per_minute' => (int) env('CHAT_GUEST_MESSAGE_RATE', 30),
        'broadcast_auth_per_minute' => (int) env('CHAT_BROADCAST_AUTH_RATE', 60),
        'operator_login_per_minute' => (int) env('CHAT_OPERATOR_LOGIN_RATE', 5),
        'operator_messages_per_minute' => (int) env('CHAT_OPERATOR_MESSAGE_RATE', 30),
    ],
    'operator_token_ttl_days' => (int) env('OPERATOR_TOKEN_TTL_DAYS', 90),
];
