<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class WebPushDelivery
{
    public function send(PushSubscription $subscription, array $payload): void
    {
        $push = app(WebPush::class);
        $report = $push->sendOneNotification(Subscription::create($subscription->subscription), json_encode($payload, JSON_THROW_ON_ERROR));
        if ($report->isSuccess()) {
            $subscription->update(['last_success_at' => now(), 'last_error_code' => null]);

            return;
        }
        if ($report->isSubscriptionExpired()) {
            $subscription->delete();

            return;
        }
        $status = $report->getResponse()?->getStatusCode() ?? 0;
        $subscription->update(['last_error_code' => $status]);
        if ($status === 0 || $status === 429 || $status >= 500) {
            throw new RuntimeException('Push service temporarily unavailable ('.$status.').');
        }
    }
}
