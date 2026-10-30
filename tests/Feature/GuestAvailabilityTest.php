<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        return Room::create(['name' => 'Suite', 'type' => 'Suite', 'price_per_night' => 50000, 'is_available' => true, 'status' => 'available']);
    }

    private function dates(Room $room): array
    {
        return ['room_id' => $room->id, 'check_in' => today()->addDays(5)->toDateString(), 'check_out' => today()->addDays(7)->toDateString()];
    }

    public function test_hold_blocks_dates_then_expires_without_a_worker(): void
    {
        $room = $this->room();
        $dates = $this->dates($room);
        $this->actingAs(User::factory()->create())->postJson('/api/bookings', $dates)->assertCreated()->assertJsonPath('total_price', '100000.00');
        $this->getJson('/api/rooms?'.http_build_query($dates))->assertOk()->assertJsonCount(0);
        $this->actingAs(User::factory()->create())->postJson('/api/bookings', $dates)->assertUnprocessable();
        $this->travel(16)->minutes();
        $this->getJson('/api/rooms?'.http_build_query($dates))->assertOk()->assertJsonCount(1)->assertJsonPath('0.stay_total', 100000);
        $this->postJson('/api/bookings', $dates)->assertCreated();
    }

    public function test_adjacent_stays_are_available_and_guest_count_is_validated(): void
    {
        $room = $this->room();
        $dates = $this->dates($room);
        $this->actingAs(User::factory()->create())->postJson('/api/bookings', $dates)->assertCreated();
        $next = ['check_in' => $dates['check_out'], 'check_out' => today()->addDays(9)->toDateString()];
        $this->getJson('/api/rooms?'.http_build_query($next))->assertOk()->assertJsonCount(1);
        $this->getJson('/api/rooms?'.http_build_query($next + ['guests' => 9]))->assertUnprocessable();
        $this->getJson('/api/rooms?check_in='.$dates['check_in'])->assertUnprocessable();
    }

    public function test_booking_contact_and_requests_persist_without_being_public(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create())->postJson('/api/bookings', $this->dates($room) + ['guest_name' => 'Arriving Guest', 'guest_phone' => '08033333333', 'guests' => 3, 'special_requests' => 'Late arrival'])->assertCreated()->assertJsonMissingPath('booking_contact');
        $booking = Booking::first();
        $this->assertSame('Arriving Guest', $booking->booking_contact['name']);
        $this->assertSame(3, $booking->guests);
        $this->assertSame('Late arrival', $booking->special_requests);
    }

    public function test_expired_hold_cannot_initialize_payment(): void
    {
        $room = $this->room();
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson('/api/bookings', $this->dates($room))->assertCreated()->json('id');
        $this->travel(16)->minutes();
        $this->postJson('/api/payments/flutterwave/initialize', ['order_type' => 'booking', 'order_id' => $id])->assertConflict();
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_checkout_release_is_owner_only_and_does_not_cancel_paid_stays(): void
    {
        $room = $this->room();
        $owner = User::factory()->create();
        $id = $this->actingAs($owner)->postJson('/api/bookings', $this->dates($room))->assertCreated()->json('id');
        $this->actingAs(User::factory()->create())->postJson("/api/bookings/{$id}/cancel-checkout")->assertNotFound();
        $this->actingAs($owner)->postJson("/api/bookings/{$id}/cancel-checkout")->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $id, 'status' => 'cancelled']);
        $paid = Booking::create($this->dates($room) + ['user_id' => $owner->id, 'total_price' => 100000, 'status' => 'confirmed', 'paid_at' => now(), 'hold_expires_at' => now()->addMinutes(15)]);
        $this->postJson("/api/bookings/{$paid->id}/cancel-checkout")->assertConflict();
        $this->assertSame('confirmed', $paid->fresh()->status);
    }
}
