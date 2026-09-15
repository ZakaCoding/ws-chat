<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\PushSubscription;
use App\ParticipantType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchMessageNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $messageId) {}

    public function handle(): void
    {
        if (! config('push.enabled')) {
            return;
        }
        $message = Message::with('participant')->find($this->messageId);
        if ($message === null) {
            return;
        }
        $query = PushSubscription::query();
        if ($message->participant->type === ParticipantType::Guest) {
            $query->whereNotNull('personal_access_token_id');
        } else {
            $query->whereHas('participant', fn ($query) => $query->where('conversation_id', $message->conversation_id));
        }
        $query->chunkById(100, function ($subscriptions): void {
            foreach ($subscriptions as $subscription) {
                SendWebPush::dispatch($subscription->id, $this->messageId);
            }
        });
    }
}
