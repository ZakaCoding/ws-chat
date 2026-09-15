<?php

namespace Tests\Feature\Services;

use App\Models\PushSubscription;
use App\Services\WebPushDelivery;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class WebPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([201])]
    #[TestWith([410])]
    #[TestWith([400])]
    public function test_records_success_removes_expired_and_records_permanent_failure(int $status): void
    {
        $subscription = PushSubscription::factory()->create();
        $report = new MessageSentReport(new Request('POST', 'https://fcm.googleapis.com/fcm/send/test'), new Response($status), $status === 201);
        $this->mock(WebPush::class)->shouldReceive('sendOneNotification')->once()->andReturn($report);
        (new WebPushDelivery)->send($subscription, ['title' => 'Test']);
        if ($status === 410) {
            $this->assertModelMissing($subscription);
        } elseif ($status === 201) {
            $this->assertNotNull($subscription->fresh()->last_success_at);
            $this->assertNull($subscription->fresh()->last_error_code);
        } else {
            $this->assertSame(400, $subscription->fresh()->last_error_code);
        }
    }

    #[TestWith([429])]
    #[TestWith([503])]
    #[TestWith([0])]
    public function test_transient_failure_is_retryable_and_keeps_subscription(int $status): void
    {
        $subscription = PushSubscription::factory()->create();
        $report = new MessageSentReport(new Request('POST', 'https://fcm.googleapis.com/fcm/send/test'), $status ? new Response($status) : null, false);
        $this->mock(WebPush::class)->shouldReceive('sendOneNotification')->once()->andReturn($report);
        try {
            (new WebPushDelivery)->send($subscription, ['title' => 'Test']);
            $this->fail('Expected a retryable error');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('temporarily unavailable', $error->getMessage());
        }
        $this->assertModelExists($subscription);
        $this->assertSame($status, $subscription->fresh()->last_error_code);
    }
}
