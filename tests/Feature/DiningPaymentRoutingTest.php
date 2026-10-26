<?php

namespace Tests\Feature;

use App\Models\FoodOrder;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DiningPaymentRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_dining_order_notifies_guest_owner_and_operations_only_once(): void
    {
        config(['services.flutterwave.secret_hash' => 'routing-secret']);
        $guest = User::factory()->create(['role' => User::ROLE_USER]);
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $operations = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $ref = 'INS-FLW-DINING-TEST-1';

        $order = FoodOrder::create([
            'user_id' => $guest->id,
            'total_amount' => 7500,
            'status' => 'pending',
            'type' => 'room_service',
            'payment_method' => 'flutterwave',
            'flutterwave_reference' => $ref,
        ]);
        PaymentAttempt::create([
            'gateway' => 'flutterwave',
            'reference' => $ref,
            'payable_type' => FoodOrder::class,
            'payable_id' => $order->id,
            'amount' => 7500,
            'currency' => 'NGN',
            'status' => 'initiated',
        ]);

        $gateway = Mockery::mock(FlutterwaveService::class);
        $gateway->shouldReceive('verifyWebhookSignature')->twice()
            ->with('routing-secret')->andReturn(true);
        $gateway->shouldReceive('verifyTransaction')->twice()->with(9001)
            ->andReturn([
                'success' => true,
                'status' => 'SUCCESSFUL',
                'data' => [
                    'id' => 9001, 'tx_ref' => $ref,
                    'status' => 'successful', 'currency' => 'NGN',
                    'amount' => 7500,
                ],
            ]);
        $this->app->instance(FlutterwaveService::class, $gateway);

        $payload = ['event' => 'charge.completed', 'data' => [
            'id' => 9001, 'tx_ref' => $ref, 'status' => 'successful',
        ]];
        $this->postJson('/api/payments/flutterwave/webhook', $payload, [
            'verif-hash' => 'routing-secret',
        ])->assertOk();
        $this->postJson('/api/payments/flutterwave/webhook', $payload, [
            'verif-hash' => 'routing-secret',
        ])->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
        foreach ([$guest, $owner, $operations] as $recipient) {
            $this->assertSame(1, $recipient->notifications()
                ->where('data->type', 'verified_commerce_payment')
                ->where('data->order_type', 'food_order')
                ->count());
        }
    }
}
