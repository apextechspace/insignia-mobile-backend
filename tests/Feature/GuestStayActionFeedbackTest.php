<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\GuestStayResource\Pages\ListGuestStays;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuestStayActionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]));
    }

    private function stay(int $daysUntilArrival = 0): Booking
    {
        $room = Room::create([
            'name' => 'Feedback Suite', 'type' => 'Suite',
            'price_per_night' => 65000, 'status' => 'available',
        ]);

        return Booking::create([
            'user_id' => User::factory()->create()->id, 'room_id' => $room->id,
            'check_in' => today()->addDays($daysUntilArrival)->toDateString(),
            'check_out' => today()->addDays($daysUntilArrival + 2)->toDateString(),
            'total_price' => 130000, 'status' => 'confirmed',
            'paid_at' => now(), 'payment_method' => 'flutterwave',
        ]);
    }

    private function registration(): array
    {
        return ['full_name' => 'Arriving Guest', 'phone' => '+2348000000000',
            'adults' => 1, 'children' => 0, 'identity_verified' => true];
    }

    public function test_future_arrival_shows_a_visible_reason_without_changing_the_stay(): void
    {
        $booking = $this->stay(1);

        Livewire::test(ListGuestStays::class)->callTableAction('check_in', $booking, $this->registration());

        Notification::assertNotified(
            Notification::make()->title('Check-in blocked')->danger()->persistent()
                ->body("Check-in is available from {$booking->check_in} until before {$booking->check_out}. Today is " . today()->toDateString() . '.')
        );
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->assertSame('available', $booking->room->fresh()->status);
    }

    public function test_valid_check_in_and_check_out_show_success(): void
    {
        $booking = $this->stay();

        Livewire::test(ListGuestStays::class)->callTableAction('check_in', $booking, $this->registration());
        Notification::assertNotified('Guest checked in');
        $this->assertNotNull($booking->fresh()->checked_in_at);
        $this->assertSame('occupied', $booking->room->fresh()->status);

        Livewire::test(ListGuestStays::class)->callTableAction('check_out', $booking->fresh());
        Notification::assertNotified('Guest checked out');
        $this->assertNotNull($booking->fresh()->checked_out_at);
        $this->assertSame('cleaning', $booking->room->fresh()->status);
    }
}
