<?php

namespace Tests\Feature;

use App\Models\AccommodationLedger;
use App\Models\Booking;
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

        return Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'total_price' => 100000,
            'status' => 'pending',
            'payment_method' => 'flutterwave',
            'flutterwave_reference' => $reference,
        ]);
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
        $this->assertSame(1, AccommodationLedger::where('booking_id', $booking->id)
            ->where('type', 'revenue')->count());
    }

    public function test_wrong_amount_never_confirms_booking(): void
    {
        $this->assertInvalidVerification(['amount' => 500]);
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
    }
}
