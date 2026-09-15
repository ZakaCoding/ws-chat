<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\PushSubscription;
use App\Services\WebPushDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWebPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 25;

    public function __construct(public int $subscriptionId, public ?string $messageId = null) {}

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(WebPushDelivery $delivery): void
    {
        if (! config('push.enabled')) {
            return;
        }
        $subscription = PushSubscription::find($this->subscriptionId);
        if ($subscription === null) {
            return;
        }
        if (! $subscription->isActive()) {
            $subscription->delete();

            return;
        }
        $message = $this->messageId ? Message::find($this->messageId) : null;
        if ($this->messageId && $message === null) {
            return;
        }
        $operator = $subscription->personal_access_token_id !== null;
        $delivery->send($subscription, [
            'title' => $this->messageId ? ($operator ? 'New portfolio message' : 'Zaka replied') : 'Notifications are working',
            'body' => $this->messageId ? ($operator ? 'Someone left you a message. Open Zaka Desk to reply.' : 'Open your conversation to read the reply.') : 'This device can receive updates even when the chat page is closed.',
            'operator' => $operator, 'conversation_id' => $message?->conversation_id,
            'tag' => 'zaka-'.($this->messageId ?? 'push-test'),
        ]);
    }
}
