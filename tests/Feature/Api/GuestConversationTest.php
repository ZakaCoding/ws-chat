<?php

namespace Tests\Feature\Api;

use App\ConversationStatus;
use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GuestConversationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_creates_conversation_and_receives_raw_token_once(): void
    {
        $response = $this->postJson('/api/conversations');

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonStructure(['data' => ['id', 'token', 'status', 'created_at']]);

        $conversationId = $response->json('data.id');
        $token = $response->json('data.token');
        $participant = Participant::query()->where('conversation_id', $conversationId)->sole();

        $this->assertSame(43, strlen($token));
        $this->assertSame(hash('sha256', $token), $participant->token_hash);
        $this->assertNotSame($token, $participant->token_hash);
        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'status' => 'open',
        ]);
    }

    public function test_guest_restores_own_conversation(): void
    {
        [$conversationId, $token] = $this->createGuestConversation();

        $response = $this->withToken($token)->getJson('/api/conversations/'.$conversationId);

        $response->assertOk()
            ->assertJsonPath('data.id', $conversationId)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.messages', [])
            ->assertJsonMissingPath('data.token');
    }

    public function test_guest_sends_trimmed_message_and_updates_last_message_time(): void
    {
        [$conversationId, $token] = $this->createGuestConversation();
        Event::fake([MessageCreated::class]);

        $response = $this->withToken($token)->postJson(
            '/api/conversations/'.$conversationId.'/messages',
            ['body' => '  Hello from the guest.  '],
        );

        $response->assertCreated()
            ->assertJsonPath('data.conversation_id', $conversationId)
            ->assertJsonPath('data.sender.type', 'guest')
            ->assertJsonPath('data.sender.name', null)
            ->assertJsonPath('data.body', 'Hello from the guest.')
            ->assertJsonStructure(['data' => ['id', 'conversation_id', 'sender', 'body', 'created_at']]);

        $this->assertDatabaseHas('messages', [
            'id' => $response->json('data.id'),
            'conversation_id' => $conversationId,
            'body' => 'Hello from the guest.',
        ]);
        $this->assertNotNull(Conversation::query()->findOrFail($conversationId)->last_message_at);
        Event::assertDispatched(MessageCreated::class);
    }

    public function test_closed_conversation_returns_409_and_does_not_store_message(): void
    {
        [$conversationId, $token] = $this->createGuestConversation();
        Conversation::query()->findOrFail($conversationId)->update([
            'status' => ConversationStatus::Closed,
        ]);

        $response = $this->withToken($token)->postJson(
            '/api/conversations/'.$conversationId.'/messages',
            ['body' => 'Too late'],
        );

        $response->assertConflict()
            ->assertExactJson(['message' => 'Messages cannot be added to a closed conversation.']);
        $this->assertSame(0, Message::query()->count());
    }

    public function test_invalid_token_returns_401(): void
    {
        [$conversationId] = $this->createGuestConversation();

        $this->withToken('invalid-token')
            ->getJson('/api/conversations/'.$conversationId)
            ->assertUnauthorized();
    }

    public function test_missing_token_returns_401(): void
    {
        [$conversationId] = $this->createGuestConversation();

        $this->getJson('/api/conversations/'.$conversationId)
            ->assertUnauthorized();
    }

    public function test_guest_cannot_access_another_conversation(): void
    {
        [, $tokenA] = $this->createGuestConversation();
        [$conversationB] = $this->createGuestConversation();

        $this->withToken($tokenA)
            ->getJson('/api/conversations/'.$conversationB)
            ->assertUnauthorized();
    }

    public function test_whitespace_only_message_returns_422(): void
    {
        [$conversationId, $token] = $this->createGuestConversation();

        $response = $this->withToken($token)->postJson(
            '/api/conversations/'.$conversationId.'/messages',
            ['body' => " \t\n "],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body'])
            ->assertJsonPath('errors.body.0', 'The body field is required.');
        $this->assertSame(0, Message::query()->count());
    }

    public function test_message_longer_than_4096_characters_returns_422(): void
    {
        [$conversationId, $token] = $this->createGuestConversation();

        $response = $this->withToken($token)->postJson(
            '/api/conversations/'.$conversationId.'/messages',
            ['body' => str_repeat('a', 4097)],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body'])
            ->assertJsonPath('errors.body.0', 'The body field must not be greater than 4096 characters.');
        $this->assertSame(0, Message::query()->count());
    }

    public function test_message_history_survives_repeated_refetch(): void
    {
        [$conversationId, $token] = $this->createGuestConversation();
        Event::fake([MessageCreated::class]);
        $this->withToken($token)->postJson(
            '/api/conversations/'.$conversationId.'/messages',
            ['body' => 'Durable history'],
        )->assertCreated();

        $firstFetch = $this->withToken($token)->getJson('/api/conversations/'.$conversationId);
        $secondFetch = $this->withToken($token)->getJson('/api/conversations/'.$conversationId);

        $firstFetch->assertOk()->assertJsonPath('data.messages.0.body', 'Durable history');
        $secondFetch->assertOk()->assertJsonPath('data.messages', $firstFetch->json('data.messages'));
        Event::assertDispatched(MessageCreated::class);
    }

    /**
     * @return array{string, string}
     */
    private function createGuestConversation(): array
    {
        $response = $this->postJson('/api/conversations')->assertCreated();

        return [$response->json('data.id'), $response->json('data.token')];
    }
}
