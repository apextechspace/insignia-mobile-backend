<?php

namespace Tests\Feature;

use App\Filament\Pages\ManualBankSettings;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\AssistedReservationPayments;
use App\Services\ReservationConfirmationEmail;
use App\Services\ReservationTerms;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationPolicyFlowTest extends TestCase
{
    use RefreshDatabase;

    private function enablePolicy(): void
    {
        DB::table('reservation_payment_policy')->where('id', 1)->update([
            'deposits_enabled' => true, 'deposit_mode' => 'night_based', 'cancellation_terms_enabled' => true,
        ]);
    }

    public function test_staff_night_based_reservation_confirms_after_verification_and_clears_balance_after_final_payment(): void
    {
        $this->enablePolicy();
        Notification::fake();
        $desk = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $room = Room::create(['name' => 'Atala', 'type' => 'Standard Room', 'price_per_night' => 80625, 'status' => 'available', 'is_available' => true]);
        $this->actingAs($desk);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('operations'));
        Livewire::test(\App\Filament\Operations\Resources\GuestStayResource\Pages\ListGuestStays::class)
            ->callAction('newReservation', data: [
                'room_id' => $room->id, 'check_in' => today()->addDays(20)->toDateString(),
                'check_out' => today()->addDays(23)->toDateString(), 'guests' => 1,
                'guest_name' => 'Guest', 'guest_phone' => '08012345678', 'guest_email' => 'guest@example.test',
            ])->assertHasNoActionErrors();
        $stay = Booking::sole();
        Notification::assertSentOnDemand(\App\Notifications\CustomerPaymentEmail::class,
            fn ($mail) => ($mail->details['reservation_update'] ?? false) && $mail->details['title'] === 'Your room is temporarily held');
        $this->assertSame(8062500, (int) $stay->minimum_payment_kobo);
        $this->assertSame('night_based', $stay->reservation_terms['deposit_mode']);
        $this->actingAs($owner);
        foreach ([8062500, 16125000] as $index => $amount) {
            $id = DB::table('assisted_reservation_payments')->insertGetId([
                'booking_id' => $stay->id, 'submitted_by' => $desk->id, 'amount_kobo' => $amount,
                'reference' => 'ACTUAL-POS-'.$index, 'method' => 'pos', 'transaction_date' => today()->toDateString(), 'proof_path' => 'receipt.png',
            ]);
            app(AssistedReservationPayments::class)->verify($stay->fresh(), $id, $owner);
            $stay->refresh();
            $this->assertSame('confirmed', $stay->status);
            $this->assertSame($index === 1, \App\Filament\Operations\Resources\GuestStayResource::canCheckIn($stay));
        }
        $this->assertSame(0, AssistedReservationPayments::balance($stay));
        Notification::assertSentOnDemand(\App\Notifications\CustomerPaymentEmail::class,
            fn ($mail) => $mail->details['receipt'] && $mail->details['full'] === false && $mail->details['balance_kobo'] === 16125000);
        Notification::assertSentOnDemand(\App\Notifications\CustomerPaymentEmail::class,
            fn ($mail) => $mail->details['receipt'] && $mail->details['full'] === true && $mail->details['balance_kobo'] === 0);
    }

    public function test_night_thresholds_and_online_full_payment_are_independent(): void
    {
        $this->enablePolicy();
        foreach ([1 => 1, 2 => 1, 6 => 1, 7 => 3, 10 => 3] as $nights => $depositNights) {
            $total = (string) (80625 * $nights);
            $this->assertSame(8062500 * $depositNights, AssistedReservationPayments::minimum($total, $nights));
        }
        $from = today()->addDays(20)->toDateString();
        $to = today()->addDays(30)->toDateString();
        $online = ReservationTerms::snapshot($from, $to, '806250', false);
        $this->assertSame(80625000, $online['minimum_kobo']);
        $this->assertSame('full', $online['deposit_mode']);
        DB::table('reservation_payment_policy')->where('id', 1)->update(['deposits_enabled' => false]);
        $this->assertSame(80625000, AssistedReservationPayments::minimum('806250', 10));
    }

    public function test_deadlines_grace_and_saved_terms_do_not_change_with_owner_settings(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-01 10:00', 'Africa/Lagos'));
        $this->enablePolicy();
        $short = ReservationTerms::snapshot('2026-12-10', '2026-12-12', '161250', true);
        $this->assertSame('2026-12-08T14:00:00+01:00', $short['refund_deadline']);
        $long = ReservationTerms::snapshot('2026-12-20', '2026-12-27', '564375', true);
        $this->assertSame('2026-12-13T14:00:00+01:00', $long['refund_deadline']);
        DB::table('reservation_payment_policy')->where('id', 1)->update(['late_booking_grace_enabled' => true]);
        $grace = ReservationTerms::snapshot('2026-12-05', '2026-12-12', '564375', true);
        $this->assertSame('2026-12-02T10:00:00+01:00', $grace['refund_deadline']);
        $sameDay = ReservationTerms::snapshot('2026-12-01', '2026-12-08', '564375', true);
        $this->assertSame('2026-12-01T14:00:00+01:00', $sameDay['refund_deadline']);
        $this->assertSame('2026-12-02T12:00:00+01:00', $sameDay['release_review_at']);
        $room = Room::create(['name' => 'Suite', 'type' => 'Suite', 'price_per_night' => 80625]);
        $stay = Booking::create(['room_id' => $room->id, 'reserved_by' => User::factory()->create()->id,
            'check_in' => '2026-12-20', 'check_out' => '2026-12-27', 'total_price' => 564375, 'status' => 'pending', 'minimum_payment_kobo' => 24187500]);
        $terms = $stay->reservation_terms;
        DB::table('reservation_payment_policy')->where('id', 1)->update(['cancellation_terms_enabled' => false, 'deposit_mode' => 'percentage']);
        $this->assertSame($terms, $stay->fresh()->reservation_terms);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $stay->update(['total_price' => 1]);
    }

    public function test_owner_can_save_new_rules_and_staff_cannot_change_them(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        Livewire::test(ManualBankSettings::class)->set('depositsEnabled', true)->set('depositMode', 'night_based')
            ->set('cancellationTermsEnabled', true)->set('lateBookingGraceEnabled', true)->call('saveDepositPolicy')->assertHasNoErrors();
        $this->assertDatabaseHas('reservation_payment_policy', ['deposit_mode' => 'night_based', 'cancellation_terms_enabled' => 1]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $this->assertFalse(ManualBankSettings::canAccess());
    }

    public function test_partial_confirmation_and_full_payment_email_render_with_balances_and_logo(): void
    {
        $this->enablePolicy();
        $guest = User::factory()->create(['name' => '<script>Guest</script>']);
        $room = Room::create(['name' => 'Atala', 'type' => 'Standard Room', 'price_per_night' => 80625]);
        foreach ([3 => 8062500, 7 => 24187500, 1 => 8062500] as $nights => $verified) {
            $stay = Booking::create(['room_id' => $room->id, 'user_id' => $guest->id, 'reserved_by' => $guest->id,
                'check_in' => today()->addDays(20)->toDateString(), 'check_out' => today()->addDays(20 + $nights)->toDateString(),
                'total_price' => 80625 * $nights, 'minimum_payment_kobo' => $verified, 'status' => 'confirmed']);
            $details = ReservationConfirmationEmail::details($stay, $verified, $verified, 'TEST-REF', 'POS', '2026-11-19');
            $this->assertSame($nights === 1, $details['full']);
            $this->assertSame(8062500 * $nights - $verified, $details['balance_kobo']);
            Notification::sendNow($guest, new \App\Notifications\CustomerPaymentEmail($details));
            $mail = Mail::getSymfonyTransport()->messages()->last()->getOriginalMessage();
            $html = $mail->getHtmlBody();
            $this->assertStringContainsString('cid:', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringContainsString('Nigeria time', $html);
            $this->assertStringContainsString($nights === 1 ? 'Accommodation balance' : 'Balance due at check-in', $html);
            $this->assertStringContainsString('not processed automatically', $html);
        }
    }
}
