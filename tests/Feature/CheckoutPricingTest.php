<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Room;
use App\Models\User;
use App\Services\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CheckoutPricingTest extends TestCase
{
    use RefreshDatabase;

    private function expectCheckoutAmount(int $amount): void
    {
        $gateway = Mockery::mock(FlutterwaveService::class);
        $gateway->shouldReceive('createPaymentLink')->once()
            ->with(Mockery::on(fn (array $data) => (float) $data['amount'] === (float) $amount))
            ->andReturn(['success' => true, 'payment_url' => 'https://checkout.example.test/pay']);
        $this->app->instance(FlutterwaveService::class, $gateway);
    }

    public function test_room_booking_and_checkout_charge_only_the_nightly_rate_times_nights(): void
    {
        $this->actingAs(User::factory()->create());
        $room = Room::create([
            'name' => 'Pricing Suite', 'type' => 'Suite',
            'price_per_night' => 65000, 'status' => 'available', 'is_available' => true,
        ]);

        $booking = $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
        ])->assertCreated()->json();

        $this->assertEquals(130000, $booking['total_price']);
        $this->assertDatabaseHas('bookings', ['id' => $booking['id'], 'total_price' => 130000]);
        $this->expectCheckoutAmount(130000);
        $this->postJson('/api/payments/flutterwave/initialize', [
            'order_type' => 'booking', 'order_id' => $booking['id'],
        ])->assertOk();
    }

    public function test_boutique_order_and_checkout_charge_only_the_product_price_times_quantity(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::create([
            'name' => 'Pricing Product', 'description' => 'Test product', 'price' => 12000, 'stock' => 5,
            'category' => 'boutique',
        ]);

        $order = $this->postJson('/api/orders', [
            'product_id' => $product->id, 'quantity' => 2,
            'notes' => null, 'payment_reference' => null, 'delivery_location' => 'Suite',
        ])->assertCreated()->json();

        $this->assertEquals(24000, $order['total_price']);
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'total_price' => 24000]);
        $this->expectCheckoutAmount(24000);
        $this->postJson('/api/payments/flutterwave/initialize', [
            'order_type' => 'order', 'order_id' => $order['id'],
        ])->assertOk();
    }
}
