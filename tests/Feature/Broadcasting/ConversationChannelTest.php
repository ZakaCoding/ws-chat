<?php

namespace Tests\Feature\Broadcasting;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConversationChannelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_a_can_subscribe_to_conversation_a(): void
    {
        [$conversationA, $tokenA] = $this->guestConversation('token-a');

        $this->withToken($tokenA)
            ->postJson('/broadcasting/auth', $this->channelPayload($conversationA))
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_guest_a_is_denied_from_subscribing_to_conversation_b(): void
    {
        [, $tokenA] = $this->guestConversation('token-a');
        [$conversationB] = $this->guestConversation('token-b');

        $this->withToken($tokenA)
            ->postJson('/broadcasting/auth', $this->channelPayload($conversationB))
            ->assertForbidden();
    }

    public function test_guest_b_is_denied_from_subscribing_to_conversation_a(): void
    {
        [$conversationA] = $this->guestConversation('token-a');
        [, $tokenB] = $this->guestConversation('token-b');

        $this->withToken($tokenB)
            ->postJson('/broadcasting/auth', $this->channelPayload($conversationA))
            ->assertForbidden();
    }

    public function test_operator_can_subscribe_to_conversation_a(): void
    {
        [$conversationA] = $this->guestConversation('token-a');
        $operator = User::factory()->create(['is_operator' => true]);

        $this->withToken($operator->createToken('test', ['chat:read'])->plainTextToken)
            ->postJson('/broadcasting/auth', $this->channelPayload($conversationA))
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_operator_can_subscribe_to_operator_inbox(): void
    {
        $operator = User::factory()->create(['is_operator' => true]);

        $this->withToken($operator->createToken('test', ['chat:read'])->plainTextToken)
            ->postJson('/api/operator/broadcasting/auth', [
                'channel_name' => 'private-operator.inbox',
                'socket_id' => '1234.5678',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_guest_authentication_does_not_leak_between_requests(): void
    {
        [$conversationA, $tokenA] = $this->guestConversation('token-a');
        [, $tokenB] = $this->guestConversation('token-b');

        $this->withToken($tokenA)
            ->postJson('/broadcasting/auth', $this->channelPayload($conversationA))
            ->assertOk();

        $this->withToken($tokenB)
            ->postJson('/broadcasting/auth', $this->channelPayload($conversationA))
            ->assertForbidden();
    }

    /**
     * @return array{Conversation, string}
     */
    private function guestConversation(string $token): array
    {
        $conversation = Conversation::factory()->create();
        Participant::factory()->for($conversation)->create([
            'token_hash' => hash('sha256', $token),
        ]);

        return [$conversation, $token];
    }

    /**
     * @return array{channel_name: string, socket_id: string}
     */
    private function channelPayload(Conversation $conversation): array
    {
        return [
            'channel_name' => 'private-conversation.'.$conversation->id,
            'socket_id' => '1234.5678',
        ];
    }
}
