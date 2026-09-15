<?php

return [
    'enabled' => (bool) env('WEB_PUSH_ENABLED', false),
    'public_key' => env('WEB_PUSH_PUBLIC_KEY'),
    'private_key' => env('WEB_PUSH_PRIVATE_KEY'),
    'subject' => env('WEB_PUSH_SUBJECT', 'mailto:zakanoor@outlook.co.id'),
];
