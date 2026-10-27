<?php

namespace Tests\Feature;

use App\Services\FlutterwaveService;
use Mockery;
use Tests\TestCase;

class FlutterwaveWebhookSecurityTest extends TestCase
{
    public function test_webhook_rejects_an_invalid_signature(): void
    {
        config(['services.flutterwave.secret_hash' => 'test-webhook-secret']);

        $this->postJson('/api/payments/flutterwave/webhook', [
            'event' => 'charge.completed',
            'data' => ['id' => 123, 'tx_ref' => 'example', 'status' => 'successful'],
        ], ['verif-hash' => 'incorrect'])->assertStatus(401);
    }

    public function test_webhook_without_transaction_id_cannot_confirm_payment(): void
    {
        config(['services.flutterwave.secret_hash' => 'test-webhook-secret']);

        $flutterwave = Mockery::mock(FlutterwaveService::class);
        $flutterwave->shouldReceive('verifyWebhookSignature')
            ->once()->with('test-webhook-secret')->andReturn(true);
        $flutterwave->shouldNotReceive('verifyTransaction');
        $this->app->instance(FlutterwaveService::class, $flutterwave);

        // A validly signed webhook is insufficient to credit a payable.
        // Missing provider transaction ID must not invoke verification or DB writes.
        $this->postJson('/api/payments/flutterwave/webhook', [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => 'INS-FLW-BOOK-1-test',
                'status' => 'successful',
            ],
        ], ['verif-hash' => 'test-webhook-secret'])->assertOk();
    }

    public function test_opay_checkout_and_webhook_routes_are_unavailable(): void
    {
        $this->postJson('/api/payments/opay/webhook', [])
            ->assertStatus(404);
        $this->postJson('/api/payments/opay/initialize', [])
            ->assertStatus(404);
    }
}
