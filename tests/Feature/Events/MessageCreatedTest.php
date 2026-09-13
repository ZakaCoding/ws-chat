<?php

namespace Tests\Feature\Events;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class MessageCreatedTest extends TestCase
{
    use DatabaseMigrations;

    public function test_broadcast_payload_matches_rest_message_payload(): void
    {
        $operator = User::factory()->create(['name' => 'Ada Operator']);
        $conversation = Conversation::factory()->create();
        Event::fake([MessageCreated::class]);

        $response = $this->actingAs($operator)->postJson(
            '/api/operator/conversations/'.$conversation->id.'/messages',
            ['body' => 'Canonical payload'],
        );

        $response->assertCreated();
        Event::assertDispatched(MessageCreated::class, function (MessageCreated $event) use ($response, $conversation): bool {
            $this->assertSame($response->json('data'), $event->broadcastWith());
            $this->assertSame('message.created', $event->broadcastAs());
            $this->assertSame(
                'private-conversation.'.$conversation->id,
                $event->broadcastOn()[0]->name,
            );
            $this->assertArrayNotHasKey('token', $event->broadcastWith());
            $this->assertArrayNotHasKey('token_hash', $event->broadcastWith());

            return true;
        });
    }

    public function test_message_event_is_discarded_when_outer_transaction_rolls_back(): void
    {
        $conversation = Conversation::factory()->create();
        $participant = Participant::factory()->for($conversation)->create();
        Event::fake([MessageCreated::class]);

        try {
            DB::transaction(function () use ($conversation, $participant): void {
                app(MessageService::class)->createForGuest($conversation, $participant, 'Rolled back');

                throw new RuntimeException('Force rollback');
            });
        } catch (RuntimeException) {
            // The rollback is the behavior under test.
        }

        Event::assertNotDispatched(MessageCreated::class);
        $this->assertDatabaseMissing('messages', ['body' => 'Rolled back']);
    }

    public function test_message_event_declares_after_commit_dispatch(): void
    {
        $message = Message::factory()->create()->load('participant');

        $event = new MessageCreated($message);

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
    }
}
