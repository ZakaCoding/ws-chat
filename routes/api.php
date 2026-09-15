<?php

use App\Http\Controllers\Api\ConversationContactController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\Operator\AuthController;
use App\Http\Controllers\Api\Operator\BroadcastAuthController;
use App\Http\Controllers\Api\Operator\ConversationController as OperatorConversationController;
use App\Http\Controllers\Api\Operator\ConversationMessageController as OperatorConversationMessageController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Models\Conversation;
use Illuminate\Support\Facades\Route;

Route::model('conversation', Conversation::class);

Route::post('/operator/auth/login', [AuthController::class, 'login'])->middleware('throttle:operator-login');
Route::middleware(['auth:sanctum', 'operator'])->prefix('operator')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::delete('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/broadcasting/auth', BroadcastAuthController::class)->middleware(['abilities:chat:read', 'throttle:broadcast-auth']);
});

Route::get('/push/config', [PushSubscriptionController::class, 'config']);

Route::post('/conversations', [ConversationController::class, 'store'])
    ->middleware('throttle:conversation-creation');

Route::middleware('conversation.guest')->group(function (): void {
    Route::patch('/conversations/{conversation}/contact', ConversationContactController::class)->middleware('throttle:guest-messages');
    Route::post('/conversations/{conversation}/push-subscriptions', [PushSubscriptionController::class, 'store'])->middleware('throttle:guest-messages');
    Route::delete('/conversations/{conversation}/push-subscriptions', [PushSubscriptionController::class, 'destroy']);
    Route::post('/conversations/{conversation}/push-subscriptions/{subscription}/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:3,1');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationMessageController::class, 'store'])
        ->middleware('throttle:guest-messages');
});

Route::prefix('operator')->middleware(['auth:sanctum', 'operator'])->group(function (): void {
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->middleware(['abilities:chat:read', 'throttle:operator-messages']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->middleware('abilities:chat:read');
    Route::post('/push-subscriptions/{subscription}/test', [PushSubscriptionController::class, 'test'])->middleware(['abilities:chat:read', 'throttle:3,1']);
    Route::get('/conversations', [OperatorConversationController::class, 'index'])->middleware('abilities:chat:read');
    Route::get('/conversations/{conversation}', [OperatorConversationController::class, 'show'])->middleware('abilities:chat:read');
    Route::post('/conversations/{conversation}/messages', [OperatorConversationMessageController::class, 'store'])
        ->middleware(['abilities:chat:reply', 'throttle:operator-messages']);
    Route::patch('/conversations/{conversation}', [OperatorConversationController::class, 'update'])
        ->middleware('abilities:chat:reply');
});
