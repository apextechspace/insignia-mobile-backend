<?php

namespace Tests\Feature;

use App\Models\{Booking, Room, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuestRegistrationCardTest extends TestCase
{
    use RefreshDatabase;

    private function stay(): Booking
    {
        return Booking::create(['room_id' => Room::create(['name' => 'Evania', 'type' => 'Executive Room', 'price_per_night' => 100000, 'status' => 'available'])->id,
            'reserved_by' => User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS])->id,
            'check_in' => today()->addDays(2)->toDateString(), 'check_out' => today()->addDays(4)->toDateString(),
            'total_price' => 200000, 'status' => 'pending', 'hold_expires_at' => now()->addMinutes(15),
            'booking_contact' => ['name' => '<script>Guest</script>', 'phone' => '09012345678', 'email' => 'guest@example.test']]);
    }

    public function test_registration_card_is_staff_only_even_for_the_bookings_guest(): void
    {
        $stay = $this->stay(); $url = route('guest-registration-card.print', $stay);
        $this->get($url)->assertForbidden();
        foreach ([User::ROLE_STAFF, User::ROLE_AUXILIARY, User::ROLE_USER] as $role) $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
        foreach ([User::ROLE_GUEST_RELATIONS, User::ROLE_DELIVERY_PARTNER, User::ROLE_IPL_ADMIN] as $role) $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertOk();
    }

    public function test_card_uses_agreed_price_and_verified_money_without_mutating_the_booking(): void
    {
        $stay = $this->stay(); $original = $stay->getAttributes();
        DB::table('assisted_reservation_payments')->insert(['booking_id' => $stay->id, 'submitted_by' => $stay->reserved_by, 'amount_kobo' => 10000000, 'reference' => 'VERIFIED', 'method' => 'pos', 'transaction_date' => today()->toDateString(), 'proof_path' => 'proof.png', 'verified_at' => now()]);
        DB::table('assisted_reservation_payments')->insert(['booking_id' => $stay->id, 'submitted_by' => $stay->reserved_by, 'amount_kobo' => 10000000, 'reference' => 'UNVERIFIED', 'method' => 'pos', 'transaction_date' => today()->toDateString(), 'proof_path' => 'proof.png']);
        $stay->room->update(['price_per_night' => 300000]);
        $this->actingAs(User::findOrFail($stay->reserved_by));
        $response = $this->get(route('guest-registration-card.print', $stay))->assertOk()->assertSee('NGN 100,000.00')->assertSee('NGN 200,000.00')->assertDontSee('NGN 300,000.00')->assertSee('1:00 PM')->assertSee('10%')->assertSee('24 hours')->assertSee('Guest signature')->assertDontSee('<script>Guest</script>', false)->assertDontSee('fi-sidebar')->assertDontSee('livewire.js');
        $this->assertSame(10000000, $response->viewData('paid'));
        $this->assertSame(10000000, $response->viewData('balance'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertEquals($original, array_intersect_key($stay->fresh()->getAttributes(), $original));
    }

    public function test_old_terms_are_preserved_and_a_paid_card_has_zero_balance(): void
    {
        $stay = $this->stay();
        $old = $stay->reservation_terms; $old['version'] = 1; unset($old['refund_admin_fee_percent'], $old['refund_processing_hours_after_checkout']);
        $old['check_in_at'] = $stay->check_in.'T14:00:00+01:00';
        DB::table('bookings')->where('id', $stay->id)->update(['reservation_terms' => json_encode($old), 'paid_at' => now(), 'status' => 'confirmed']);
        $this->actingAs(User::findOrFail($stay->reserved_by));
        $response = $this->get(route('guest-registration-card.print', $stay))->assertOk()->assertSee('2:00 PM')->assertSee('originally agreed refund terms')->assertDontSee('10% administration fee');
        $this->assertSame(0, $response->viewData('balance'));
    }
}
