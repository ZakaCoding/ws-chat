<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebPush;
use App\Models\Participant;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PushSubscriptionController extends Controller
{
    public function config(): JsonResponse
    {
        return response()->json(['data' => ['enabled' => $this->enabled(), 'public_key' => $this->enabled() ? config('push.public_key') : null]]);
    }

    private function enabled(): bool
    {
        return config('push.enabled') && config('push.public_key') && config('push.private_key');
    }

    private function owner(Request $request): array
    {
        $participant = $request->attributes->get('conversation_participant');
        if ($participant instanceof Participant) {
            return ['owner_key' => 'guest:'.$participant->id, 'participant_id' => $participant->id, 'personal_access_token_id' => null];
        }
        $token = $request->user()->currentAccessToken();

        return ['owner_key' => 'operator:'.$token->id, 'personal_access_token_id' => $token->id, 'participant_id' => null];
    }

    private function owned(Request $request): Builder
    {
        return PushSubscription::query()->where('owner_key', $this->owner($request)['owner_key']);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->enabled(), 503, 'Push is not configured.');
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:100'],
            'keys.auth' => ['required', 'string', 'max:32'],
        ]);
        $url = parse_url($data['endpoint']);
        $host = strtolower($url['host'] ?? '');
        $allowed = $host === 'fcm.googleapis.com' || $host === 'updates.push.services.mozilla.com'
         || str_ends_with($host, '.push.services.mozilla.com') || $host === 'web.push.apple.com'
         || str_ends_with($host, '.notify.windows.com');
        if (! $allowed || isset($url['user']) || isset($url['pass']) || ($url['port'] ?? 443) !== 443) {
            throw ValidationException::withMessages(['endpoint' => 'Use a subscription from a supported browser push service.']);
        }
        $public = base64_decode(strtr($data['keys']['p256dh'], '-_', '+/'), true);
        $auth = base64_decode(strtr($data['keys']['auth'], '-_', '+/'), true);
        if ($public === false || strlen($public) !== 65 || $public[0] !== "\x04" || $auth === false || strlen($auth) !== 16) {
            throw ValidationException::withMessages(['keys' => 'The push subscription keys are invalid.']);
        }
        $owner = $this->owner($request);
        $hash = hash('sha256', $data['endpoint']);
        abort_if($this->owned($request)->where('endpoint_hash', '!=', $hash)->count() >= 10, 422, 'Too many devices. Remove an existing subscription first.');
        $subscription = PushSubscription::query()->updateOrCreate(
            ['owner_key' => $owner['owner_key'], 'endpoint_hash' => $hash],
            [...$owner, 'subscription' => $data],
        );

        return response()->json(['data' => ['id' => $subscription->id, 'last_success_at' => $subscription->last_success_at, 'last_error_code' => $subscription->last_error_code]]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $this->owned($request)->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['message' => 'Notifications disabled.']);
    }

    public function test(Request $request): JsonResponse
    {
        abort_unless($this->enabled(), 503);
        $owned = $this->owned($request)->findOrFail($request->route('subscription'));
        SendWebPush::dispatch($owned->id);

        return response()->json(['message' => 'Test notification queued.'], 202);
    }
}
