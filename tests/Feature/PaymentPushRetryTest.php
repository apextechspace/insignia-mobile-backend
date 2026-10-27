<?php

namespace Tests\Feature;

use App\Jobs\DeliverPaymentPush;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PaymentPushRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_delivery_sends_once(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => ['status' => 'ok']], 200)]);
        $user = User::factory()->create(['expo_push_token' => 'ExponentPushToken[test-token]']);
        (new DeliverPaymentPush($user->id, 'Paid', 'Verified'))->handle();
        Http::assertSentCount(1);
    }

    public function test_provider_failure_throws_for_queue_retry(): void
    {
        Http::fake(['exp.host/*' => Http::response(['errors' => ['temporary']], 503)]);
        $user = User::factory()->create(['expo_push_token' => 'ExponentPushToken[test-token]']);
        $job = new DeliverPaymentPush($user->id, 'Paid', 'Verified');
        $this->assertSame(3, $job->tries);
        $this->expectException(RuntimeException::class);
        $job->handle();
    }

    public function test_missing_push_token_does_not_retry(): void
    {
        Http::fake();
        $user = User::factory()->create(['expo_push_token' => null]);
        (new DeliverPaymentPush($user->id, 'Paid', 'Verified'))->handle();
        Http::assertNothingSent();
    }
}
