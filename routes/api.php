<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\Operator\ConversationController as OperatorConversationController;
use App\Http\Controllers\Api\Operator\ConversationMessageController as OperatorConversationMessageController;
use Illuminate\Support\Facades\Route;

Route::post('/conversations', [ConversationController::class, 'store'])
    ->middleware('throttle:conversation-creation');

Route::middleware('conversation.guest')->group(function (): void {
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ConversationMessageController::class, 'store'])
        ->middleware('throttle:guest-messages');
});

Route::prefix('operator')->middleware(['web', 'auth'])->group(function (): void {
    Route::get('/conversations', [OperatorConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [OperatorConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [OperatorConversationMessageController::class, 'store']);
    Route::patch('/conversations/{conversation}', [OperatorConversationController::class, 'update']);
});
