<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Services\RoomAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoomDiscountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_badge_does_not_reduce_the_final_price_or_change_an_existing_booking(): void
    {
        $room = Room::create([
            'name' => 'Standard', 'type' => 'Standard Room', 'price_per_night' => 75000,
            'discount_enabled' => true, 'discount_percentage' => 25, 'discount_label' => 'Introductory offer',
            'is_available' => true, 'status' => 'available',
        ]);
        $this->getJson('/api/rooms')->assertOk()->assertJsonFragment([
            'discount_enabled' => true, 'discount_percentage' => '25.00', 'discount_label' => 'Introductory offer',
        ]);
        $this->actingAs(\App\Models\User::factory()->create());
        $booking = $this->postJson('/api/bookings', [
            'room_id' => $room->id, 'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
        ])->assertCreated()->assertJsonPath('total_price', '150000.00')->json();
        $room->update(['price_per_night' => 100000, 'discount_enabled' => false]);
        $gateway = \Mockery::mock(\App\Services\FlutterwaveService::class);
        $gateway->shouldReceive('createPaymentLink')->once()
            ->with(\Mockery::on(fn (array $data) => (float) $data['amount'] === 150000.0))
            ->andReturn(['success' => true, 'payment_url' => 'https://checkout.example.test/pay']);
        $this->app->instance(\App\Services\FlutterwaveService::class, $gateway);
        $this->postJson('/api/payments/flutterwave/initialize', [
            'order_type' => 'booking', 'order_id' => $booking['id'],
        ])->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $booking['id'], 'total_price' => 150000]);
        $this->assertSame('200000.00', RoomAvailability::quote($room, '2026-12-01', '2026-12-03'));
    }

    public function test_disabled_discount_preserves_existing_rates_and_does_not_add_vat(): void
    {
        $room = Room::create([
            'name' => 'Standard', 'type' => 'Standard Room', 'price_per_night' => 80625,
            'discount_enabled' => false, 'discount_percentage' => 25,
        ]);
        $this->assertSame('161250.00', RoomAvailability::quote($room, '2026-12-01', '2026-12-03'));
        $room->refresh();
        $this->assertFalse($room->discount_enabled);
        $this->assertSame('25.00', $room->discount_percentage);
    }

    public function test_an_enabled_offer_requires_a_valid_percentage(): void
    {
        $this->expectException(ValidationException::class);
        Room::create([
            'name' => 'Standard', 'type' => 'Standard Room', 'price_per_night' => 75000,
            'discount_enabled' => true, 'discount_percentage' => 100,
        ]);
    }
}
