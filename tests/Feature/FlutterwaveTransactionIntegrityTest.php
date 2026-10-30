<?php

namespace Tests\Feature;

use App\Models\AccommodationLedger;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\Room;
use App\Models\User;
use App\Services\FlutterwaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FlutterwaveTransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';
    private const REFERENCE = 'INS-FLW-BOOK-1-test-reference';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.flutterwave.secret_hash' => self::SECRET]);
    }

    private function makeBooking(?string $reference = self::REFERENCE): Booking
    {
        $user = User::factory()->create();
        $room = Room::create([
            'name' => 'Atala',
            'type' => 'Suite',
            'price_per_night' => 50000,
            'status' => 'available',
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'total_price' => 100000,
            'status' => 'pending',
            'payment_method' => 'flutterwave',
            'flutterwave_reference' => $reference,
        ]);
        PaymentAttempt::create([
            'gateway' => 'flutterwave',
            'reference' => $reference,
            'payable_type' => Booking::class,
            'payable_id' => $booking->id,
            'amount' => 100000,
            'currency' => 'NGN',
            'status' => 'initiated',
        ]);
        return $booking;
    }

    private function webhook(FlutterwaveService $service, string $reference = self::REFERENCE): void
    {
        $service->shouldReceive('verifyWebhookSignature')
            ->with(self::SECRET)->andReturn(true);
        $this->app->instance(FlutterwaveService::class, $service);
    }

    private function sendWebhook(string $reference = self::REFERENCE, int $id = 5151)
    {
        return $this->postJson('/api/payments/flutterwave/webhook', [
            'event' => 'charge.completed',
            'data' => ['tx_ref' => $reference, 'id' => $id, 'status' => 'successful'],
        ], ['verif-hash' => self::SECRET]);
    }

    private function verified(array $overrides = []): array
    {
        return [
            'success' => true,
            'status' => 'SUCCESSFUL',
            'data' => array_merge([
                'id' => 5151,
                'status' => 'successful',
                'tx_ref' => self::REFERENCE,
                'currency' => 'NGN',
                'amount' => 100000,
            ], $overrides),
        ];
    }

    public function test_successful_verified_payment_creates_one_ledger_entry_even_if_replayed(): void
    {
        $booking = $this->makeBooking();
        $service = Mockery::mock(FlutterwaveService::class);
        $this->webhook($service);
        $service->shouldReceive('verifyTransaction')->twice()->with(5151)
            ->andReturn($this->verified());

        $this->sendWebhook()->assertOk();
        $this->sendWebhook()->assertOk();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertNotNull($booking->paid_at);
        $this->assertSame(1, $booking->user->notifications()->where('data->type', 'booking_payment')->count());
        $this->assertSame(1, AccommodationLedger::where('booking_id', $booking->id)
            ->where('type', 'revenue')->count());
        $this->assertDatabaseHas('payment_attempts', [
            'reference' => self::REFERENCE,
            'status' => 'confirmed',
            'provider_transaction_id' => '5151',
        ]);
    }

    public function test_wrong_amount_never_confirms_booking(): void
    {
        $this->assertInvalidVerification(['amount' => 500]);
    }

    public function test_payment_after_hold_expiry_is_reconciled_once_without_confirming_a_room(): void
    {
        $booking = $this->makeBooking();
        $booking->update(['hold_expires_at' => now()->subMinute()]);
        $service = Mockery::mock(FlutterwaveService::class);
        $this->webhook($service);
        $service->shouldReceive('verifyTransaction')->twice()->with(5151)->andReturn($this->verified());
        $this->sendWebhook()->assertOk();
        $this->sendWebhook()->assertOk();
        $this->assertNull($booking->fresh()->paid_at);
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertDatabaseHas('payment_attempts', ['reference' => self::REFERENCE, 'status' => 'needs_review']);
        $this->assertSame(0, AccommodationLedger::where('booking_id', $booking->id)->count());
        $this->assertDatabaseCount('payment_reconciliation_cases', 1);
    }

    public function test_wrong_currency_never_confirms_booking(): void
    {
        $this->assertInvalidVerification(['currency' => 'USD']);
    }

    public function test_wrong_reference_never_confirms_booking(): void
    {
        $this->assertInvalidVerification(['tx_ref' => 'OTHER-TRANSACTION']);
    }

    public function test_mismatched_provider_id_never_confirms_booking(): void
    {
        $this->assertInvalidVerification(['id' => 5152]);
    }

    private function assertInvalidVerification(array $overrides): void
    {
        $booking = $this->makeBooking();
        $service = Mockery::mock(FlutterwaveService::class);
        $this->webhook($service);
        $service->shouldReceive('verifyTransaction')->once()->with(5151)
            ->andReturn($this->verified($overrides));

        $this->sendWebhook()->assertOk();
        $booking->refresh();

        $this->assertSame('pending', $booking->status);
        $this->assertNull($booking->paid_at);
        $this->assertSame(0, AccommodationLedger::where('booking_id', $booking->id)->count());
    }

    public function test_paid_booking_does_not_displace_existing_confirmed_stay(): void
    {
        $booking = $this->makeBooking();
        $other = Booking::create([
            'user_id' => User::factory()->create()->id,
            'room_id' => $booking->room_id,
            'check_in' => $booking->check_in,
            'check_out' => $booking->check_out,
            'total_price' => 100000,
            'status' => 'confirmed',
            'paid_at' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        $service = Mockery::mock(FlutterwaveService::class);
        $this->webhook($service);
        $service->shouldReceive('verifyTransaction')->once()->with(5151)
            ->andReturn($this->verified());

        $this->sendWebhook()->assertOk();

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame('confirmed', $other->fresh()->status);
        $this->assertDatabaseHas('payment_reconciliation_cases', [
            'payment_reference' => self::REFERENCE,
            'reason' => 'room_date_conflict',
            'status' => 'needs_review',
        ]);
    }

    public function test_old_checkout_reference_still_confirms_after_new_retry(): void
    {
        $booking = $this->makeBooking();
        $booking->update(['flutterwave_reference' => 'INS-FLW-BOOK-1-second-attempt']);

        PaymentAttempt::create([
            'gateway' => 'flutterwave',
            'reference' => 'INS-FLW-BOOK-1-second-attempt',
            'payable_type' => Booking::class,
            'payable_id' => $booking->id,
            'amount' => 100000,
            'currency' => 'NGN',
            'status' => 'initiated',
        ]);

        $service = Mockery::mock(FlutterwaveService::class);
        $this->webhook($service);
        $service->shouldReceive('verifyTransaction')->once()->with(5151)
            ->andReturn($this->verified());

        $this->sendWebhook()->assertOk();

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(self::REFERENCE, $booking->fresh()->flutterwave_reference);
        $this->assertDatabaseHas('payment_attempts', [
            'reference' => self::REFERENCE,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'reference' => 'INS-FLW-BOOK-1-second-attempt',
            'status' => 'initiated',
        ]);
    }

    public function test_second_successful_payment_is_flagged_for_reconciliation(): void
    {
        $booking = $this->makeBooking();
        $booking->update([
            'status' => 'confirmed',
            'paid_at' => now(),
            'flutterwave_transaction_id' => '4000',
        ]);

        $service = Mockery::mock(FlutterwaveService::class);
        $this->webhook($service);
        $service->shouldReceive('verifyTransaction')->once()->with(5151)
            ->andReturn($this->verified());

        $this->sendWebhook()->assertOk();

        $this->assertDatabaseHas('payment_reconciliation_cases', [
            'payment_reference' => self::REFERENCE,
            'reason' => 'already_paid_other_attempt',
            'status' => 'needs_review',
        ]);
    }
}
