<?php

namespace Tests\Feature;

use App\Models\{Booking, Room, User};
use App\Services\AssistedReservationPayments;
use App\Filament\Operations\Resources\GuestStayResource\Pages\ViewGuestStay;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Notification, Storage};
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AssistedPaymentControlsTest extends TestCase
{
    use RefreshDatabase;

    private function stay(): Booking
    {
        $staff = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $room = Room::create(['name' => 'Control Room', 'type' => 'Standard Room', 'price_per_night' => 100000, 'status' => 'available']);
        return Booking::create(['reserved_by' => $staff->id, 'room_id' => $room->id, 'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDays(2)->toDateString(), 'total_price' => 100000, 'minimum_payment_kobo' => 5000000, 'status' => 'pending', 'hold_expires_at' => now()->addMinutes(15)]);
    }

    private function proof(Booking $stay, string $reference, int $amount = 5000000): int
    {
        return DB::table('assisted_reservation_payments')->insertGetId(['booking_id' => $stay->id, 'submitted_by' => $stay->reserved_by, 'amount_kobo' => $amount, 'reference' => $reference, 'method' => 'pos', 'proof_path' => 'proof.png', 'transaction_date' => today()->toDateString()]);
    }

    private function submit(Booking $stay, string $reference, string $amount = '50000')
    {
        $this->actingAs(User::findOrFail($stay->reserved_by));
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1uoAAAAASUVORK5CYII='));
        return Livewire::test(ViewGuestStay::class, ['record' => $stay->id])->mountAction('recordPosPayment')
            ->set('mountedActionsData.0.proof', [$file])->set('mountedActionsData.0.reference', $reference)->set('mountedActionsData.0.amount', $amount)
            ->set('mountedActionsData.0.method', 'pos')->set('mountedActionsData.0.transaction_date', today(config('property.timezone'))->toDateString())->callMountedAction();
    }

    public function test_only_one_pending_proof_and_rejection_keeps_history_before_corrected_submission(): void
    {
        Notification::fake();
        $stay = $this->stay();
        $id = $this->proof($stay, 'INCORRECT');
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $this->actingAs($owner); Filament::setCurrentPanel(Filament::getPanel('operations'));
        Livewire::test(ViewGuestStay::class, ['record' => $stay->id])->assertActionDisabled('recordPosPayment');
        app(AssistedReservationPayments::class)->reject($stay, $id, $owner, 'Wrong receipt attached');
        $this->assertDatabaseHas('assisted_reservation_payments', ['id' => $id, 'rejected_by' => $owner->id, 'rejection_reason' => 'Wrong receipt attached']);
        $this->assertSame(0, AssistedReservationPayments::verified($stay));
        $this->assertFalse(AssistedReservationPayments::pending($stay)->exists());
        $this->submit($stay, 'CORRECTED')->assertHasNoActionErrors();
        $this->assertDatabaseCount('assisted_reservation_payments', 2);
        try { app(AssistedReservationPayments::class)->verify($stay, $id, $owner); $this->fail('Rejected proof was accepted'); }
        catch (ValidationException) { $this->assertSame(0, AssistedReservationPayments::verified($stay)); }
    }

    public function test_reference_already_credited_elsewhere_cannot_be_submitted(): void
    {
        $first = $this->stay();
        app(AssistedReservationPayments::class)->verify($first, $this->proof($first, 'USED-REFERENCE'), User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $second = $this->stay();
        $this->submit($second, 'used-reference')->assertHasErrors();
        $this->assertSame(0, AssistedReservationPayments::entries($second)->count());
    }

    public function test_confirmed_reservation_requires_exact_balance_and_cannot_accept_a_third_payment(): void
    {
        $stay = $this->stay(); $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $service = app(AssistedReservationPayments::class);
        $service->verify($stay, $this->proof($stay, 'FIRST'), $owner);
        $this->submit($stay->fresh(), 'SMALL-BALANCE', '100')->assertHasErrors();
        $this->assertSame(1, AssistedReservationPayments::entries($stay)->count());
        $balance = $this->proof($stay, 'BALANCE');
        $service->verify($stay->fresh(), $balance, $owner);
        $this->assertSame(10000000, AssistedReservationPayments::verified($stay));
        $this->assertFalse(AssistedReservationPayments::canCollect($stay->fresh()));
        try { $service->reject($stay, $balance, $owner, 'Cannot undo verified money'); $this->fail('Verified proof was rejected'); }
        catch (ValidationException) { $this->assertSame(10000000, AssistedReservationPayments::verified($stay)); }
    }

    public function test_old_unverified_third_proof_is_not_counted_as_income_or_shown_as_verified(): void
    {
        $stay = $this->stay(); $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $service = app(AssistedReservationPayments::class);
        $service->verify($stay, $this->proof($stay, 'ONE'), $owner);
        $service->verify($stay->fresh(), $this->proof($stay, 'TWO'), $owner);
        $this->proof($stay, 'OLD-UNUSED');
        $this->actingAs($owner); Filament::setCurrentPanel(Filament::getPanel('operations'));
        Livewire::test(ViewGuestStay::class, ['record' => $stay->id])->assertSee('Not credited · Reservation already paid in full')->assertActionHidden('recordPosPayment')->assertActionHidden('verifyPosPayment');
        $this->assertSame(10000000, AssistedReservationPayments::verified($stay));
        $rows = app(\App\Services\IplFinancialStatement::class)->report(today()->toDateString(), today()->toDateString());
        $this->assertSame(10000000, $rows['inflow_cents']);
    }
}
