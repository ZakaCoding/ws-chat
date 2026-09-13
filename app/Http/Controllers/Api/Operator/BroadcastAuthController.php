<?php

namespace App\Http\Controllers\Api\Operator;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastAuthController
{
    public function __invoke(Request $request): mixed
    {
        return Broadcast::auth($request);
    }
}
