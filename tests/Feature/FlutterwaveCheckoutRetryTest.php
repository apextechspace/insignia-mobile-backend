<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\Room;
use App\Models\User;
use App\Services\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FlutterwaveCheckoutRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_checkout_retains_previous_attempt_and_reference(): void
    {
        $user = User::factory()->create();
        $room = Room::create([
            'name' => 'Junonia',
            'type' => 'Suite',
            'price_per_night' => 50000,
            'status' => 'available',
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'check_in' => now()->addDays(6)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'total_price' => 100000,
            'status' => 'pending',
        ]);

        $service = Mockery::mock(FlutterwaveService::class);
        $service->shouldReceive('createPaymentLink')->twice()
            ->andReturnUsing(fn ($data) => [
                'success' => true,
                'payment_url' => 'https://checkout.example.test/' . $data['reference'],
            ]);
        $this->app->instance(FlutterwaveService::class, $service);

        $this->actingAs($user);
        $payload = ['order_type' => 'booking', 'order_id' => $booking->id];

        $first = $this->postJson('/api/payments/flutterwave/initialize', $payload)
            ->assertOk()->json('reference');
        $second = $this->postJson('/api/payments/flutterwave/initialize', $payload)
            ->assertOk()->json('reference');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, PaymentAttempt::where('payable_type', Booking::class)
            ->where('payable_id', $booking->id)->count());

        $this->assertDatabaseHas('payment_attempts', [
            'reference' => $first,
            'status' => 'initiated',
            'amount' => 100000,
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'reference' => $second,
            'status' => 'initiated',
        ]);
        $this->assertSame($second, $booking->fresh()->flutterwave_reference);
    }

    public function test_paid_booking_cannot_open_another_checkout(): void
    {
        $user = User::factory()->create();
        $room = Room::create([
            'name' => 'Morpho',
            'type' => 'Suite',
            'price_per_night' => 50000,
            'status' => 'available',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'check_in' => now()->addDays(6)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'total_price' => 100000,
            'status' => 'confirmed',
            'paid_at' => now(),
            'payment_method' => 'flutterwave',
        ]);

        $this->actingAs($user)->postJson('/api/payments/flutterwave/initialize', [
            'order_type' => 'booking',
            'order_id' => $booking->id,
        ])->assertStatus(409);

        $this->assertSame(0, PaymentAttempt::count());
    }
}
