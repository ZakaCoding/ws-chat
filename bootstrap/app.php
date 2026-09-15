<?php

use App\Http\Middleware\AuthenticateConversationBroadcast;
use App\Http\Middleware\AuthenticateConversationGuest;
use App\Http\Middleware\AuthenticateOperator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => [AuthenticateConversationBroadcast::class, 'throttle:broadcast-auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'conversation.guest' => AuthenticateConversationGuest::class,
            'operator' => AuthenticateOperator::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
