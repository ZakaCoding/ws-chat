<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationClosedException extends Exception implements ShouldntReport
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Messages cannot be added to a closed conversation.',
        ], 409);
    }
}
