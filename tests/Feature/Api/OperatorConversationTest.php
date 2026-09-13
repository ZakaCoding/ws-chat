<?php

namespace Tests\Feature\Api;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OperatorConversationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_operator_list_returns_401(): void
    {
        $this->getJson('/api/operator/conversations')->assertUnauthorized();
    }

    public function test_operator_lists_conversations_by_last_message_time_with_pagination(): void
    {
        $operator = User::factory()->create();
        $older = Conversation::factory()->create(['last_message_at' => '2026-09-13 10:00:00']);
        $newer = Conversation::factory()->create(['last_message_at' => '2026-09-13 11:00:00']);
        $empty = Conversation::factory()->create(['last_message_at' => null]);

        $response = $this->actingAs($operator)
            ->getJson('/api/operator/conversations?per_page=2');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
        $this->assertNotSame($empty->id, $response->json('data.0.id'));
    }

    public function test_operator_views_conversation_with_message_history(): void
    {
        $operator = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $guest = Participant::factory()->for($conversation)->create();
        $message = Message::factory()->for($conversation)->for($guest)->create([
            'body' => 'Existing message',
        ]);

        $response = $this->actingAs($operator)
            ->getJson('/api/operator/conversations/'.$conversation->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.messages.0.id', $message->id)
            ->assertJsonPath('data.messages.0.body', 'Existing message')
            ->assertJsonPath('data.messages.0.sender.type', 'guest');
    }

    public function test_operator_replies_to_open_conversation(): void
    {
        $operator = User::factory()->create(['name' => 'Support Operator']);
        $conversation = Conversation::factory()->create();
        Event::fake([MessageCreated::class]);

        $response = $this->actingAs($operator)->postJson(
            '/api/operator/conversations/'.$conversation->id.'/messages',
            ['body' => '  How can I help?  '],
        );

        $response->assertCreated()
            ->assertJsonPath('data.sender.type', 'operator')
            ->assertJsonPath('data.sender.name', 'Support Operator')
            ->assertJsonPath('data.body', 'How can I help?');
        $this->assertDatabaseHas('messages', [
            'id' => $response->json('data.id'),
            'conversation_id' => $conversation->id,
            'body' => 'How can I help?',
        ]);
        $this->assertDatabaseHas('participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $operator->id,
            'type' => 'operator',
            'token_hash' => null,
        ]);
        Event::assertDispatched(MessageCreated::class);
    }

    public function test_operator_closes_conversation(): void
    {
        $operator = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $response = $this->actingAs($operator)->patchJson(
            '/api/operator/conversations/'.$conversation->id,
            ['status' => 'closed'],
        );

        $response->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => 'closed',
        ]);
    }

    public function test_operator_reply_to_closed_conversation_returns_409(): void
    {
        $operator = User::factory()->create();
        $conversation = Conversation::factory()->closed()->create();

        $this->actingAs($operator)->postJson(
            '/api/operator/conversations/'.$conversation->id.'/messages',
            ['body' => 'Cannot send'],
        )->assertConflict();

        $this->assertSame(0, Message::query()->count());
    }
}
