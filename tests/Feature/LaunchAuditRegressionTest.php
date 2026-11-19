<?php

namespace Tests\Feature;

use App\Models\{Booking, Room, User};
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaunchAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_auxiliary_user_root_redirects_to_its_own_panel(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_AUXILIARY]));
        $this->get('/')->assertRedirect('/auxiliary');
    }

    private function room(): Room
    {
        return Room::create(['name' => 'Audit Room', 'type' => 'Standard Room', 'price_per_night' => 100000, 'status' => 'available', 'is_available' => true]);
    }

    public function test_owner_booking_form_uses_server_quote_and_blocks_overlapping_holds(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $guest = User::factory()->create();
        $room = $this->room();
        $data = ['user_id' => $guest->id, 'room_id' => $room->id, 'check_in' => today()->addDays(3)->toDateString(), 'check_out' => today()->addDays(5)->toDateString(), 'status' => 'confirmed', 'total_price' => 1];
        $page = \App\Filament\Resources\BookingResource\Pages\CreateBooking::class;
        Livewire::test($page)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $booking = Booking::sole();
        $this->assertSame('200000.00', $booking->total_price);
        $this->assertSame('pending', $booking->status);
        $this->assertNotNull($booking->hold_expires_at);
        Livewire::test($page)->fillForm($data)->call('create')->assertHasFormErrors(['room_id']);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_guest_maintenance_alerts_reach_owner_manager_and_front_desk_but_not_auxiliary_or_cleaner(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $desk = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $cleaner = User::factory()->create(['role' => User::ROLE_STAFF]);
        $aux = User::factory()->create(['role' => User::ROLE_AUXILIARY]);
        $this->actingAs(User::factory()->create())->postJson('/api/maintenance', ['category' => 'Plumbing', 'title' => 'Leaking tap', 'description' => '<b>Kitchen sink</b>'])->assertCreated();
        foreach ([$owner, $manager, $desk] as $recipient) {
            $data = $recipient->notifications()->sole()->data;
            $this->assertSame('filament', $data['format']);
            $this->assertSame('maintenance', $data['type']);
            $this->assertStringContainsString('&lt;b&gt;', $data['body']);
        }
        $this->assertSame(0, $cleaner->notifications()->count());
        $this->assertSame(0, $aux->notifications()->count());
    }

    public function test_manager_maintenance_review_is_not_limited_to_first_twelve_arrivals(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $room = $this->room();
        for ($i = 1; $i <= 13; $i++) {
            Booking::create(['room_id' => $room->id, 'check_in' => today()->addDays($i)->toDateString(), 'check_out' => today()->addDays($i + 1)->toDateString(), 'total_price' => 100000, 'status' => 'confirmed', 'paid_at' => now()]);
        }
        $maintenance = Room::create(['name' => 'Maintenance Room', 'type' => 'Suite', 'price_per_night' => 100000, 'status' => 'maintenance']);
        $arrival = Booking::create(['room_id' => $maintenance->id, 'check_in' => today()->addDays(30)->toDateString(), 'check_out' => today()->addDays(31)->toDateString(), 'total_price' => 100000, 'status' => 'confirmed', 'paid_at' => now()]);
        $data = (new \App\Filament\Operations\Widgets\OperationsDashboardWidget)->getViewData();
        $this->assertCount(12, $data['upcomingReservations']);
        $this->assertSame($arrival->id, $data['maintenanceArrivals']->sole()->id);
    }
}
