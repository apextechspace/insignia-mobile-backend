<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\StayOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StayOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function stay(bool $paid = true): Booking
    {
        $room = Room::create([
            'name' => 'Orchid Suite', 'type' => 'Suite',
            'price_per_night' => 65000, 'status' => 'available',
        ]);

        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'total_price' => 130000,
            'status' => $paid ? 'confirmed' : 'pending',
            'paid_at' => $paid ? now() : null,
            'payment_method' => 'flutterwave',
        ]);
    }

    public function test_paid_guest_checkin_and_checkout_update_room_and_stay(): void
    {
        $booking = $this->stay();
        $operator = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $service = app(StayOperationsService::class);

        $service->checkIn($booking, $operator);
        $this->assertNotNull($booking->fresh()->checked_in_at);
        $this->assertSame('occupied', $booking->room->fresh()->status);

        $service->checkOut($booking, $operator);
        $this->assertNotNull($booking->fresh()->checked_out_at);
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame('cleaning', $booking->room->fresh()->status);
    }

    public function test_unpaid_guest_cannot_check_in(): void
    {
        $booking = $this->stay(false);
        $operator = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $this->expectException(ValidationException::class);
        app(StayOperationsService::class)->checkIn($booking, $operator);
    }

    public function test_guest_role_cannot_check_in(): void
    {
        $booking = $this->stay();
        $guest = User::factory()->create(['role' => User::ROLE_USER]);
        $this->expectException(ValidationException::class);
        app(StayOperationsService::class)->checkIn($booking, $guest);
    }

    public function test_duplicate_check_in_is_rejected(): void
    {
        $booking = $this->stay();
        $operator = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $service = app(StayOperationsService::class);
        $service->checkIn($booking, $operator);
        $this->expectException(ValidationException::class);
        $service->checkIn($booking, $operator);
    }

    public function test_room_under_maintenance_cannot_check_in(): void
    {
        $booking = $this->stay();
        $booking->room->update(['status' => 'maintenance']);
        $operator = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $this->expectException(ValidationException::class);
        app(StayOperationsService::class)->checkIn($booking, $operator);
    }
}
