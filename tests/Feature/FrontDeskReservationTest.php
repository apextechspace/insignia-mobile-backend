<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\GuestStayResource\Pages\ListGuestStays;
use App\Filament\Operations\Resources\GuestStayResource;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FrontDeskReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_pos_deposit_secures_dates_and_balance_enables_check_in(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $room = Room::create(['name' => 'Atala', 'type' => 'Suite', 'price_per_night' => 100000]);
        \Illuminate\Support\Facades\DB::table('reservation_payment_policy')->where('id', 1)->update(['deposits_enabled' => true]);
        $this->assertSame(15000000, \App\Services\AssistedReservationPayments::minimum('300000.00'));
        $stay = Booking::create([
            'reserved_by' => $manager->id, 'room_id' => $room->id, 'total_price' => 300000,
            'minimum_payment_kobo' => 15000000, 'status' => 'pending', 'hold_expires_at' => now()->addMinutes(15),
            'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDays(4)->toDateString(),
        ]);
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $page = \App\Filament\Operations\Resources\GuestStayResource\Pages\ViewGuestStay::class;
        $this->actingAs($manager);
        \Illuminate\Support\Facades\Storage::fake('local');
        $originalHold = $stay->hold_expires_at;
        foreach (['POS-A'] as $reference) {
            $proof = \Illuminate\Http\UploadedFile::fake()->createWithContent('pos.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1uoAAAAASUVORK5CYII='));
            Livewire::test($page, ['record' => $stay->id])
                ->mountAction('recordPosPayment')
                ->set('mountedActionsData.0.proof', [$proof])
                ->set('mountedActionsData.0.amount', '150000')
                ->set('mountedActionsData.0.method', $reference === 'POS-A' ? 'pos' : 'bank_transfer')
                ->set('mountedActionsData.0.reference', $reference)
                ->set('mountedActionsData.0.transaction_date', today(config('property.timezone'))->toDateString())
                ->callMountedAction()->assertHasNoActionErrors();
                $reviewHold = $stay->fresh()->hold_expires_at;
                $this->assertTrue($reviewHold->gt($originalHold));
                $this->assertSame('Payment under review', GuestStayResource::stayStage($stay->fresh()));
                $this->travel(16)->minutes();
                $this->assertTrue(\App\Services\RoomAvailability::blocking($stay->check_in, $stay->check_out)
                    ->whereKey($stay->id)->exists());
        }
        $this->assertDatabaseCount('assisted_reservation_payments', 1);
        $this->actingAs($owner);
        Livewire::test($page, ['record' => $stay->id])
            ->callAction('verifyPosPayment', data: ['payment_id' => 1, 'verified' => true])->assertHasNoActionErrors();
        $stay->refresh();
        $this->assertSame('confirmed', $stay->status);
        $this->assertNull($stay->hold_expires_at);
        $this->assertFalse(GuestStayResource::canCheckIn($stay));
        Livewire::test($page, ['record' => $stay->id])
            ->assertActionHasLabel('recordPosPayment', 'Collect Balance')
            ->mountAction('recordPosPayment')->assertActionDataSet(['amount' => '150000.00']);
        $this->actingAs($manager);
        Livewire::test($page, ['record' => $stay->id])->assertActionHidden('verifyPosPayment');
        \Illuminate\Support\Facades\DB::table('reservation_payment_policy')->where('id', 1)
            ->update(['vrl_can_verify_pos' => true]);
        $frontDesk = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $this->assertFalse(\App\Services\AssistedReservationPayments::canVerify($frontDesk));
        $proof = \Illuminate\Http\UploadedFile::fake()->createWithContent('balance.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1uoAAAAASUVORK5CYII='));
        Livewire::test($page, ['record' => $stay->id])->mountAction('recordPosPayment')
            ->set('mountedActionsData.0.proof', [$proof])->set('mountedActionsData.0.amount', '150000')
            ->set('mountedActionsData.0.method', 'bank_transfer')->set('mountedActionsData.0.reference', 'POS-B')
            ->set('mountedActionsData.0.transaction_date', today(config('property.timezone'))->toDateString())
            ->callMountedAction()->assertHasNoActionErrors();
        Livewire::test($page, ['record' => $stay->id])
            ->callAction('verifyPosPayment', data: ['payment_id' => 2, 'verified' => true])->assertHasNoActionErrors();
        $this->assertTrue(GuestStayResource::canCheckIn($stay->fresh()));
        $this->assertSame('mixed', $stay->fresh()->payment_method);
        $this->assertDatabaseHas('assisted_reservation_payments', ['id' => 2, 'verified_by' => $manager->id]);
        $this->assertDatabaseCount('accommodation_ledgers', 1);
    }

    public function test_manager_can_reserve_but_cannot_overlap_a_paid_stay(): void
    {
        $guest = User::factory()->create();
        $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $room = Room::create(['name' => 'Atala', 'type' => 'Suite', 'price_per_night' => 100000]);
        $dates = [
            'room_id' => $room->id,
            'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(5)->toDateString(),
        ];
        Booking::create($dates + [
            'user_id' => $guest->id, 'total_price' => 300000, 'status' => 'confirmed', 'paid_at' => now(),
        ]);
        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $form = $dates + ['guests' => 1, 'guest_name' => 'Phone guest', 'guest_phone' => '08012345678'];

        Livewire::test(ListGuestStays::class)->callAction('newReservation', data: $form)->assertActionHalted('newReservation');
        $this->assertDatabaseCount('bookings', 1);

        $form['check_in'] = $dates['check_out'];
        $form['check_out'] = today()->addDays(6)->toDateString();
        Livewire::test(ListGuestStays::class)->callAction('newReservation', data: $form)->assertHasNoActionErrors();
        $this->assertDatabaseHas('bookings', ['reserved_by' => $manager->id, 'check_in' => $dates['check_out']]);
    }

    public function test_front_desk_hold_blocks_online_booking_until_expiry(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $room = Room::create(['name' => 'Atala', 'type' => 'Suite', 'price_per_night' => 100000]);
        $dates = [
            'room_id' => $room->id,
            'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(5)->toDateString(),
            'guests' => 2,
        ];

        Livewire::test(ListGuestStays::class)->callAction('newReservation', data: $dates + [
            'guest_name' => 'Walk-in guest',
            'guest_phone' => '08012345678',
        ])->assertHasNoActionErrors();

        $hold = Booking::sole();
        $this->assertNull($hold->user_id);
        $this->assertNull($hold->paid_at);
        $this->assertSame($staff->id, $hold->reserved_by);
        $this->assertSame('300000.00', $hold->total_price);
        $this->assertSame('Walk-in guest', $hold->booking_contact['name']);
        $this->assertTrue($hold->hold_expires_at->isFuture());
        $this->assertFalse(GuestStayResource::canCheckIn($hold));
        $this->get(GuestStayResource::getUrl('view', ['record' => $hold], panel: 'operations'))
            ->assertOk()->assertSee('Walk-in guest')->assertSee('Room hold expires');

        $this->actingAs(User::factory()->create());
        $this->postJson('/api/bookings', $dates)->assertUnprocessable()->assertJsonValidationErrors('room_id');
        $this->travel(16)->minutes();
        $this->postJson('/api/bookings', $dates)->assertCreated();
    }
}
