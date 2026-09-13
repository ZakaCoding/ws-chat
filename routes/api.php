<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\Operator\AuthController;
use App\Http\Controllers\Api\Operator\BroadcastAuthController;
use App\Http\Controllers\Api\Operator\ConversationController as OperatorConversationController;
use App\Http\Controllers\Api\Operator\ConversationMessageController as OperatorConversationMessageController;
use Illuminate\Support\Facades\Route;

Route::post('/operator/auth/login', [AuthController::class, 'login'])->middleware('throttle:operator-login');
Route::middleware(['auth:sanctum', 'operator'])->prefix('operator')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::delete('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/broadcasting/auth', BroadcastAuthController::class)->middleware(['abilities:chat:read', 'throttle:broadcast-auth']);
});

Route::post('/conversations', [ConversationController::class, 'store'])
    ->middleware('throttle:conversation-creation');

Route::middleware('conversation.guest')->group(function (): void {
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationMessageController::class, 'store'])
        ->middleware('throttle:guest-messages');
});

Route::prefix('operator')->middleware(['auth:sanctum', 'operator'])->group(function (): void {
    Route::get('/conversations', [OperatorConversationController::class, 'index'])->middleware('abilities:chat:read');
    Route::get('/conversations/{conversation}', [OperatorConversationController::class, 'show'])->middleware('abilities:chat:read');
    Route::post('/conversations/{conversation}/messages', [OperatorConversationMessageController::class, 'store'])
        ->middleware(['abilities:chat:reply', 'throttle:operator-messages']);
    Route::patch('/conversations/{conversation}', [OperatorConversationController::class, 'update'])
        ->middleware('abilities:chat:reply');
});
