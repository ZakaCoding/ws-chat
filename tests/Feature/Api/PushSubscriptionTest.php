<?php

namespace Tests\Feature\Api;

use App\Jobs\SendWebPush;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/test-device'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => rtrim(strtr(base64_encode("\x04".str_repeat("\x01", 64)), '+/', '-_'), '='), 'auth' => rtrim(strtr(base64_encode(str_repeat('x', 16)), '+/', '-_'), '=')]];
    }

    private function enablePush(): void
    {
        config(['push.enabled' => true, 'push.public_key' => 'public-test', 'push.private_key' => 'private-test']);
    }

    public function test_config_never_exposes_private_key(): void
    {
        $this->enablePush();
        $this->getJson('/api/push/config')->assertOk()->assertExactJson(['data' => ['enabled' => true, 'public_key' => 'public-test']]);
    }

    public function test_guest_registers_once_and_can_test_and_remove_own_device(): void
    {
        $this->enablePush();
        Queue::fake([SendWebPush::class]);
        $session = $this->postJson('/api/conversations')->json('data');
        $url = '/api/conversations/'.$session['id'].'/push-subscriptions';
        $this->postJson($url, $this->payload())->assertUnauthorized();
        $response = $this->withToken($session['token'])->postJson($url, $this->payload())->assertOk();
        $this->withToken($session['token'])->postJson($url, $this->payload())->assertOk()->assertJsonPath('data.id', $response->json('data.id'));
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertStringNotContainsString('fcm.googleapis.com', PushSubscription::first()->getRawOriginal('subscription'));
        $this->withToken($session['token'])->postJson($url.'/'.$response->json('data.id').'/test')->assertAccepted();
        Queue::assertPushed(SendWebPush::class, 1);
        $this->withToken($session['token'])->deleteJson($url, ['endpoint' => $this->payload()['endpoint']])->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_rejects_arbitrary_destinations_and_invalid_keys(): void
    {
        $this->enablePush();
        $session = $this->postJson('/api/conversations')->json('data');
        $url = '/api/conversations/'.$session['id'].'/push-subscriptions';
        foreach (['http://fcm.googleapis.com/test', 'https://127.0.0.1/test', 'https://fcm.googleapis.com.evil.example/test', 'https://fcm.googleapis.com:8443/test', 'https://user:pass@fcm.googleapis.com/test'] as $endpoint) {
            $this->withToken($session['token'])->postJson($url, $this->payload($endpoint))->assertUnprocessable()->assertJsonValidationErrors('endpoint');
        }
        $payload = $this->payload();
        $payload['keys']['auth'] = 'bad';
        $this->withToken($session['token'])->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('keys');
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_guest_cannot_test_another_guests_subscription(): void
    {
        $this->enablePush();
        Queue::fake([SendWebPush::class]);
        $a = $this->postJson('/api/conversations')->json('data');
        $b = $this->postJson('/api/conversations')->json('data');
        $id = $this->withToken($a['token'])->postJson('/api/conversations/'.$a['id'].'/push-subscriptions', $this->payload())->json('data.id');
        $this->withToken($b['token'])->postJson('/api/conversations/'.$b['id'].'/push-subscriptions/'.$id.'/test')->assertNotFound();
        Queue::assertNotPushed(SendWebPush::class);
    }

    public function test_operator_registration_requires_role_and_read_ability_and_logout_removes_it(): void
    {
        $this->enablePush();
        $user = User::factory()->create(['is_operator' => false]);
        $token = $user->createToken('device', ['chat:read'])->plainTextToken;
        $this->withToken($token)->postJson('/api/operator/push-subscriptions', $this->payload())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $operator = User::factory()->create(['is_operator' => true]);
        $limited = $operator->createToken('limited', ['chat:reply'])->plainTextToken;
        $this->withToken($limited)->postJson('/api/operator/push-subscriptions', $this->payload())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $token = $operator->createToken('device', ['chat:read'])->plainTextToken;
        $this->withToken($token)->postJson('/api/operator/push-subscriptions', $this->payload())->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->withToken($token)->deleteJson('/api/operator/auth/logout')->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_registration_returns_503_until_keys_are_configured(): void
    {
        $session = $this->postJson('/api/conversations')->json('data');
        $this->withToken($session['token'])->postJson('/api/conversations/'.$session['id'].'/push-subscriptions', $this->payload())->assertServiceUnavailable();
        $this->assertDatabaseCount('push_subscriptions',0);
    }
}
