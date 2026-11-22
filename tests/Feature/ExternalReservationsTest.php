<?php

namespace Tests\Feature;

use App\Models\{Booking, Room, User};
use App\Services\{ExternalReservations, AssistedReservationPayments, IplFinancialStatement, StayOperationsService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Notification, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExternalReservationsTest extends TestCase
{
    use RefreshDatabase;

    private function input(): array
    {
        return ['source' => 'booking_com', 'reference' => 'OTA-123', 'unit_reference' => '1',
            'room_id' => Room::create(['name' => 'External Room', 'type' => 'Standard Room', 'price_per_night' => 100000, 'status' => 'available', 'is_available' => true])->id,
            'check_in' => today()->addDays(2)->toDateString(), 'check_out' => today()->addDays(4)->toDateString(), 'guests' => 2,
            'total' => '175000.00', 'guest_name' => 'External Guest', 'guest_phone' => '09012345678', 'guest_email' => 'guest@example.test',
            'payment_arrangement' => 'pay_at_property', 'channel_terms' => 'Original channel policy: refundable until the stated channel deadline.', 'confirmation_checked' => true];
    }

    public function test_confirmed_external_booking_preserves_price_and_terms_blocks_dates_but_is_not_income(): void
    {
        Notification::fake(); $staff = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $data = $this->input(); $stay = app(ExternalReservations::class)->create($data, $staff);
        $this->assertSame('175000.00', $stay->total_price);
        $this->assertSame('confirmed', $stay->status); $this->assertNull($stay->paid_at); $this->assertNull($stay->hold_expires_at); $this->assertNull($stay->user_id);
        $this->assertTrue($stay->reservation_terms['external']);
        $this->assertSame($data['channel_terms'], $stay->reservation_terms['channel_terms']);
        $this->assertArrayNotHasKey('refund_admin_fee_percent', $stay->reservation_terms);
        $this->assertDatabaseCount('accommodation_ledgers', 0);
        $report = app(IplFinancialStatement::class)->report(today()->toDateString(), today()->toDateString());
        $this->assertSame(0, $report['inflow_cents']);
        $this->actingAs(User::factory()->create())->postJson('/api/bookings', ['room_id' => $stay->room_id, 'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'guests' => 1])->assertUnprocessable();
        $this->assertTrue(AssistedReservationPayments::canCollect($stay));
    }

    public function test_existing_paid_stay_blocks_external_import_without_mutation(): void
    {
        Notification::fake(); $data = $this->input();
        Booking::create(['room_id' => $data['room_id'], 'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'total_price' => 200000, 'status' => 'confirmed', 'paid_at' => now()]);
        try { app(ExternalReservations::class)->create($data, User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER])); $this->fail('Overlap accepted'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('room_id', $exception->errors()); }
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_channel_reference_and_room_line_are_unique_but_multi_room_reservations_are_supported(): void
    {
        Notification::fake(); $service = app(ExternalReservations::class); $staff = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $service->create($this->input(), $staff);
        $second = $this->input();
        try { $service->create($second, $staff); $this->fail('Duplicate import accepted'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('reference', $exception->errors()); }
        $this->assertDatabaseCount('bookings', 1);
        $second['unit_reference'] = '2'; $service->create($second, $staff);
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_channel_collected_payment_requires_reviewer_evidence_without_creating_bank_income(): void
    {
        Notification::fake(); Storage::fake('local'); $service = app(ExternalReservations::class); $staff = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $data = $this->input(); $data['payment_arrangement'] = 'channel_collects';
        $stay = $service->create($data, $staff);
        $this->assertFalse(AssistedReservationPayments::canCollect($stay));
        $this->assertFalse(ExternalReservations::guestPaymentCleared($stay));
        Storage::disk('local')->put('external-channel-evidence/confirmation.pdf', 'synthetic test proof');
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $service->verifyGuestChannelPayment($stay, $owner, 'external-channel-evidence/confirmation.pdf', 'Checked original channel record and full guest payment');
        $stay->refresh(); $this->assertTrue(ExternalReservations::guestPaymentCleared($stay)); $this->assertNull($stay->paid_at);
        $this->assertFalse(AssistedReservationPayments::canCollect($stay));
        $this->assertDatabaseCount('accommodation_ledgers', 0);
        $this->assertSame(0, app(IplFinancialStatement::class)->report(today()->toDateString(), today()->toDateString())['inflow_cents']);
        $this->travelTo(\Carbon\CarbonImmutable::parse($stay->check_in.' 14:00', config('property.timezone')));
        app(StayOperationsService::class)->checkIn($stay, $staff, ['full_name' => 'External Guest', 'phone' => '09012345678', 'adults' => 2, 'children' => 0, 'identity_verified' => true]);
        $this->assertNotNull($stay->fresh()->checked_in_at);
        $this->assertDatabaseCount('accommodation_ledgers', 0);
        $json = $stay->fresh()->toArray(); $this->assertArrayNotHasKey('external_details', $json);
        $this->assertSame('channel_verified', $json['external_payment_status']);
        $this->assertStringNotContainsString('confirmation.pdf', json_encode($json));
    }

    public function test_front_desk_cannot_verify_channel_payment_or_cancel_external_reservations(): void
    {
        Notification::fake(); Storage::fake('local'); $service = app(ExternalReservations::class); $staff = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $data = $this->input(); $data['payment_arrangement'] = 'channel_collects'; $stay = $service->create($data, $staff);
        Storage::disk('local')->put('external-channel-evidence/test.pdf', 'proof');
        try { $service->verifyGuestChannelPayment($stay, $staff, 'external-channel-evidence/test.pdf', 'Not authorised'); $this->fail('Front desk verified payment'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        try { $service->cancel($stay, $staff, 'Cancelled in channel'); $this->fail('Front desk cancelled'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
    }

    public function test_property_payments_are_verified_normally_and_count_once(): void
    {
        Notification::fake(); $stay = app(ExternalReservations::class)->create($this->input(), User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]));
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        foreach (['PART-ONE', 'PART-TWO'] as $reference) {
            $id = DB::table('assisted_reservation_payments')->insertGetId(['booking_id' => $stay->id, 'submitted_by' => $stay->reserved_by, 'amount_kobo' => 8750000, 'reference' => $reference, 'method' => 'pos', 'transaction_date' => today()->toDateString(), 'proof_path' => 'proof.png']);
            app(AssistedReservationPayments::class)->verify($stay->fresh(), $id, $owner);
        }
        $this->assertNotNull($stay->fresh()->paid_at);
        $this->assertSame(17500000, app(IplFinancialStatement::class)->report(today()->toDateString(), today()->toDateString())['inflow_cents']);
    }

    public function test_manager_can_resolve_unknown_arrangement_but_not_overwrite_a_payment_and_cancellation_releases_dates(): void
    {
        Notification::fake(); $service = app(ExternalReservations::class); $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $data = $this->input(); $data['payment_arrangement'] = 'review_required'; $stay = $service->create($data, $manager);
        $this->assertFalse(AssistedReservationPayments::canCollect($stay));
        $service->setPaymentArrangement($stay, $manager, 'pay_at_property', 'Original channel confirms guest pays the hotel');
        $this->assertTrue(AssistedReservationPayments::canCollect($stay->fresh()));
        $this->assertCount(1, $stay->fresh()->external_details['arrangement_reviews']);
        $service->cancel($stay, $manager, 'Cancellation checked in original channel REF-CANCEL-123');
        $this->assertSame('cancelled', $stay->fresh()->status);
        $this->assertFalse(\App\Services\RoomAvailability::blocking($stay->check_in, $stay->check_out)->where('room_id', $stay->room_id)->exists());
        $this->assertDatabaseCount('accommodation_ledgers', 0);
    }

    public function test_guest_relations_can_record_external_booking_through_the_responsive_form(): void
    {
        Notification::fake(); $staff = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $this->actingAs($staff); \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('operations'));
        \Livewire\Livewire::test(\App\Filament\Operations\Resources\GuestStayResource\Pages\ListGuestStays::class)
            ->callAction('recordExternalReservation', data: $this->input())->assertHasNoActionErrors();
        $stay = Booking::sole();
        \Livewire\Livewire::test(\App\Filament\Operations\Resources\GuestStayResource\Pages\ViewGuestStay::class, ['record' => $stay->id])
            ->assertSee('Booking.com')->assertSee('OTA-123')->assertSee('Reserved · Payment due at property')->assertActionVisible('recordPosPayment')->assertActionHidden('verifyChannelGuestPayment')->assertActionHidden('reviewExternalArrangement');
        $this->assertNull($stay->paid_at);
    }

    public function test_external_card_and_guest_updates_never_demand_duplicate_channel_payment(): void
    {
        Notification::fake(); Storage::fake('local'); $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $data = $this->input(); $data['payment_arrangement'] = 'channel_collects'; $data['channel_terms'] = 'Original channel policy applies, including its own cancellation agreement.';
        $stay = app(ExternalReservations::class)->create($data, $owner);
        $this->actingAs($owner);
        $this->get(route('guest-registration-card.print', $stay))->assertOk()->assertSee('Channel payment under review')->assertSee($data['channel_terms'])->assertDontSee('10% administration fee');
        Storage::disk('local')->put('external-channel-evidence/test.pdf', 'proof');
        app(ExternalReservations::class)->verifyGuestChannelPayment($stay, $owner, 'external-channel-evidence/test.pdf', 'Full channel payment checked');
        $this->get(route('guest-registration-card.print', $stay))->assertOk()->assertSee('Guest channel payment verified')->assertSee('NGN 0.00');
        $message = rawurldecode(\App\Services\ReservationGuestCommunication::whatsappUrl($stay->fresh()));
        $this->assertStringContainsString('Balance before check-in: NGN 0.00', $message);
        $this->assertStringNotContainsString('Full verified payment is required', $message);
    }

    public function test_property_money_cancellation_is_owner_only_and_never_forges_a_refund(): void
    {
        Notification::fake(); $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]); $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $stay = app(ExternalReservations::class)->create($this->input(), $manager);
        $id = DB::table('assisted_reservation_payments')->insertGetId(['booking_id' => $stay->id, 'submitted_by' => $manager->id, 'amount_kobo' => 17500000, 'reference' => 'PROPERTY-PAID', 'method' => 'bank_transfer', 'transaction_date' => today()->toDateString(), 'proof_path' => 'proof.png']);
        app(AssistedReservationPayments::class)->verify($stay, $id, $owner);
        try { app(ExternalReservations::class)->cancel($stay->fresh(), $manager, 'Cancellation confirmed by channel'); $this->fail('Manager cancelled property money without owner review'); }
        catch (ValidationException) { $this->assertSame('confirmed', $stay->fresh()->status); }
        app(ExternalReservations::class)->cancel($stay->fresh(), $owner, 'Owner checked channel cancellation; payout will be reviewed separately');
        $this->assertNotNull($stay->fresh()->paid_at); $this->assertSame('cancelled', $stay->fresh()->status);
        $this->assertSame(0, \App\Models\AccommodationLedger::where('type', 'refund')->count());
        $this->assertSame(17500000, app(IplFinancialStatement::class)->report(today()->toDateString(), today()->toDateString())['inflow_cents']);
    }

    public function test_guest_booking_api_cannot_forge_external_payment_verification(): void
    {
        Notification::fake(); $data = $this->input();
        $this->actingAs(User::factory()->create())->postJson('/api/bookings', [
            'room_id' => $data['room_id'], 'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'guests' => 1,
            'external_source' => 'booking_com', 'external_reference' => 'FORGED',
            'external_details' => ['payment_arrangement' => 'channel_collects', 'guest_payment_verified_at' => now()->toIso8601String()],
        ])->assertCreated();
        $stay = Booking::sole();
        $this->assertNull($stay->external_source); $this->assertNull($stay->external_details); $this->assertNull($stay->paid_at);
        $this->assertFalse(ExternalReservations::guestPaymentCleared($stay));
        $this->assertSame('200000.00', $stay->total_price);
    }
}
