<?php

namespace App\Providers;

use App\Auth\ConversationGuestGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Auth::extend('conversation-guest', fn (): ConversationGuestGuard => new ConversationGuestGuard);

        RateLimiter::for('conversation-creation', fn (Request $request): Limit => Limit::perMinute(
            (int) config('chat.rate_limits.conversation_creation_per_minute'),
        )->by('conversation-create|'.$request->ip()));

        RateLimiter::for('guest-messages', function (Request $request): Limit {
            $conversation = $request->route('conversation');
            $conversationId = is_object($conversation) ? (string) $conversation->getKey() : (string) $conversation;
            $credentialKey = hash('sha256', (string) $request->bearerToken());

            return Limit::perMinute((int) config('chat.rate_limits.guest_messages_per_minute'))
                ->by('guest-message|'.$conversationId.'|'.$credentialKey.'|'.$request->ip());
        });

        RateLimiter::for('broadcast-auth', function (Request $request): Limit {
            $operator = $request->user();
            $principalKey = $operator instanceof Authenticatable
                ? 'operator:'.$operator->getAuthIdentifier()
                : 'guest:'.hash('sha256', (string) $request->bearerToken());

            return Limit::perMinute((int) config('chat.rate_limits.broadcast_auth_per_minute'))
                ->by('broadcast-auth|'.$principalKey.'|'.$request->ip());
        });
        RateLimiter::for('operator-login', fn (Request $request): Limit => Limit::perMinute(
            (int) config('chat.rate_limits.operator_login_per_minute'),
        )->by('operator-login|'.strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('operator-messages', fn (Request $request): Limit => Limit::perMinute(
            (int) config('chat.rate_limits.operator_messages_per_minute'),
        )->by('operator-message|'.($request->user()?->getAuthIdentifier() ?? 'unknown').'|'.$request->ip()));
    }
}
