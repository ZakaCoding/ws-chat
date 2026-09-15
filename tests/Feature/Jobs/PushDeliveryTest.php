<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchMessageNotifications;
use App\Jobs\SendWebPush;
use App\Models\Message;
use App\Models\Participant;
use App\Models\PushSubscription;
use App\Models\User;
use App\ParticipantType;
use App\Services\WebPushDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_visitor_message_notifies_operator_without_an_open_desk(): void
    {
        config(['push.enabled' => true]);
        Queue::fake([SendWebPush::class]);
        $operator = User::factory()->create(['is_operator' => true]);
        $token = $operator->createToken('phone', ['chat:read']);
        $subscription = PushSubscription::factory()->create(['participant_id' => null, 'personal_access_token_id' => $token->accessToken->id, 'owner_key' => 'operator:'.$token->accessToken->id]);
        $message = Message::factory()->create();
        (new DispatchMessageNotifications($message->id))->handle();
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->subscriptionId === $subscription->id && $job->messageId === $message->id);
    }

    public function test_reply_only_targets_the_guest_in_that_conversation(): void
    {
        config(['push.enabled' => true]);
        Queue::fake([SendWebPush::class]);
        $guest = Participant::factory()->create();
        $other = Participant::factory()->create();
        $own = PushSubscription::factory()->create(['participant_id' => $guest->id]);
        PushSubscription::factory()->create(['participant_id' => $other->id]);
        $operator = Participant::factory()->create(['conversation_id' => $guest->conversation_id, 'type' => ParticipantType::Operator]);
        $message = Message::factory()->create(['conversation_id' => $guest->conversation_id, 'participant_id' => $operator->id]);
        (new DispatchMessageNotifications($message->id))->handle();
        Queue::assertPushed(SendWebPush::class, fn ($job) => $job->subscriptionId === $own->id);
        Queue::assertPushed(SendWebPush::class, 1);
    }

    public function test_expired_operator_token_is_removed_without_delivery(): void
    {
        config(['push.enabled' => true]);
        $operator = User::factory()->create(['is_operator' => true]);
        $token = $operator->createToken('old', ['chat:read'], now()->subDay());
        $subscription = PushSubscription::factory()->create(['participant_id' => null, 'personal_access_token_id' => $token->accessToken->id]);
        $delivery = $this->mock(WebPushDelivery::class);
        $delivery->shouldNotReceive('send');
        (new SendWebPush($subscription->id))->handle($delivery);
        $this->assertModelMissing($subscription);
    }

    public function test_payload_does_not_expose_message_text_or_credentials(): void
    {
        config(['push.enabled' => true]);
        $guest = Participant::factory()->create();
        $subscription = PushSubscription::factory()->create(['participant_id' => $guest->id]);
        $message = Message::factory()->create(['conversation_id' => $guest->conversation_id, 'body' => 'Private project details']);
        $delivery = $this->mock(WebPushDelivery::class);
        $delivery->shouldReceive('send')->once()->withArgs(fn ($sub, $payload) => $sub->id === $subscription->id && $payload['body'] === 'Open your conversation to read the reply.' && ! str_contains(json_encode($payload), 'Private project details'));
        (new SendWebPush($subscription->id, $message->id))->handle($delivery);
    }
}
