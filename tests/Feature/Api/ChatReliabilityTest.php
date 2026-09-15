<?php

namespace Tests\Feature\Api;

use App\Events\MessageCreated;
use App\Jobs\DispatchMessageNotifications;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_returns_original_message_without_duplicate_broadcast(): void
    {
        Event::fake([MessageCreated::class]);
        Queue::fake([DispatchMessageNotifications::class]);
        config(['push.enabled' => true]);
        $session = $this->postJson('/api/conversations')->json('data');
        $body = ['body' => 'My project', 'client_message_id' => '2731a75c-4e87-4e55-a938-3f21d951c334'];
        $url = '/api/conversations/'.$session['id'].'/messages';
        $first = $this->withToken($session['token'])->postJson($url, $body)->assertCreated();
        $this->withToken($session['token'])->postJson($url, $body)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('messages', 1);
        Event::assertDispatchedTimes(MessageCreated::class, 1);
        Queue::assertPushed(DispatchMessageNotifications::class, 1);
    }

    public function test_reusing_identifier_for_different_body_returns_409(): void
    {
        Event::fake([MessageCreated::class]);
        $session = $this->postJson('/api/conversations')->json('data');
        $url = '/api/conversations/'.$session['id'].'/messages';
        $id = '2731a75c-4e87-4e55-a938-3f21d951c334';
        $this->withToken($session['token'])->postJson($url, ['body' => 'First', 'client_message_id' => $id])->assertCreated();
        $this->withToken($session['token'])->postJson($url, ['body' => 'Changed', 'client_message_id' => $id])->assertConflict();
        $this->assertDatabaseCount('messages', 1);
        Event::assertDispatchedTimes(MessageCreated::class, 1);
    }

    public function test_identical_text_with_distinct_identifiers_is_not_lost(): void
    {
        Event::fake([MessageCreated::class]);
        $session = $this->postJson('/api/conversations')->json('data');
        foreach (['2731a75c-4e87-4e55-a938-3f21d951c334', '2731a75c-4e87-4e55-a938-3f21d951c335'] as $id) {
            $this->withToken($session['token'])->postJson('/api/conversations/'.$session['id'].'/messages', ['body' => 'Hello', 'client_message_id' => $id])->assertCreated();
        }
        $this->assertDatabaseCount('messages', 2);
        Event::assertDispatchedTimes(MessageCreated::class, 2);
    }

    public function test_email_is_encrypted_removable_and_scoped_to_guest(): void
    {
        $session = $this->postJson('/api/conversations')->json('data');
        $other = $this->postJson('/api/conversations')->json('data');
        $url = '/api/conversations/'.$session['id'].'/contact';
        $this->withToken($other['token'])->patchJson($url, ['email' => 'visitor@example.com'])->assertUnauthorized();
        $this->withToken($session['token'])->patchJson($url, ['email' => 'visitor@example.com'])->assertOk()->assertJsonPath('data.contact_email', 'visitor@example.com');
        $conversation = Conversation::findOrFail($session['id']);
        $this->assertStringNotContainsString('visitor@example.com', $conversation->getRawOriginal('contact_email'));
        $this->withToken($session['token'])->patchJson($url, ['email' => null])->assertOk()->assertJsonPath('data.contact_email', null);
        $this->assertNull($conversation->fresh()->contact_email);
    }

    public function test_invalid_contact_and_message_identifiers_return_422(): void
    {
        $session = $this->postJson('/api/conversations')->json('data');
        $this->withToken($session['token'])->patchJson('/api/conversations/'.$session['id'].'/contact', ['email' => 'not-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->withToken($session['token'])->postJson('/api/conversations/'.$session['id'].'/messages', ['body' => 'Hello', 'client_message_id' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('client_message_id');
        $this->assertDatabaseCount('messages', 0);
    }
}
