<?php

namespace Tests\Feature;

use App\Filament\Pages\IplMasterStatement;
use App\Models\AccommodationLedger;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\FoodOrder;
use App\Models\Room;
use App\Models\User;
use App\Services\IplFinancialStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IplMasterStatementTest extends TestCase
{
    use RefreshDatabase;

    private function report(string $source = 'all'): array
    {
        return app(IplFinancialStatement::class)->report(today()->subDay()->toDateString(), today()->addDay()->toDateString(), $source);
    }

    public function test_ipl_only_can_access_the_financial_statement(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $this->assertTrue(IplMasterStatement::canAccess());

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $this->assertFalse(IplMasterStatement::canAccess());

        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]));
        $this->assertFalse(IplMasterStatement::canAccess());
    }

    public function test_accommodation_is_counted_once_and_only_if_paid(): void
    {
        $room = Room::create(['name' => 'IPL Suite', 'type' => 'Suite', 'price_per_night' => 50000, 'status' => 'available']);
        $guest = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $guest->id, 'room_id' => $room->id,
            'check_in' => today()->addDays(3)->toDateString(),
            'check_out' => today()->addDays(5)->toDateString(),
            'total_price' => 100000, 'status' => 'pending',
        ]);
        $this->assertSame(0, $this->report()['inflow_cents']);

        $booking->update(['status' => 'confirmed', 'payment_method' => 'flutterwave', 'paid_at' => now()]);
        $statement = $this->report();
        $this->assertSame(10000000, $statement['inflow_cents']);
        $this->assertCount(1, $statement['rows']);
        $this->assertSame('accommodation', $statement['rows']->first()['source']);
    }

    public function test_dining_payment_and_approved_expense_have_correct_signed_totals(): void
    {
        $user = User::factory()->create();
        FoodOrder::create([
            'user_id' => $user->id, 'type' => 'pickup', 'total_amount' => 8000,
            'status' => 'paid', 'payment_method' => 'flutterwave', 'paid_at' => now(),
        ]);
        Expense::create([
            'title' => 'Laundry', 'category' => 'consumables_restock',
            'amount' => 3000, 'currency' => 'NGN', 'payment_method' => 'cash',
            'logged_by' => $user->id, 'status' => 'approved', 'expense_date' => now(),
        ]);
        Expense::create([
            'title' => 'Unapproved', 'category' => 'other',
            'amount' => 2500, 'currency' => 'NGN', 'payment_method' => 'cash',
            'logged_by' => $user->id, 'status' => 'pending_approval', 'expense_date' => now(),
        ]);
        $r = $this->report();
        $this->assertSame(800000, $r['inflow_cents']);
        $this->assertSame(300000, $r['outflow_cents']);
        $this->assertSame(500000, $r['net_cents']);
        $this->assertCount(2, $r['rows']);
        $this->assertSame(800000, $this->report('dining')['net_cents']);
        $this->assertSame(-300000, $this->report('expense')['net_cents']);
    }

    public function test_refund_is_included_only_when_refund_and_booking_states_match(): void
    {
        $room = Room::create(['name' => 'Refund Suite', 'type' => 'Suite', 'price_per_night' => 50000, 'status' => 'available']);
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id, 'room_id' => $room->id,
            'check_in' => today()->addDays(3)->toDateString(),
            'check_out' => today()->addDays(5)->toDateString(),
            'total_price' => 10000, 'status' => 'pending',
        ]);
        $booking->update(['paid_at' => now(), 'status' => 'confirmed', 'payment_method' => 'flutterwave']);

        AccommodationLedger::create([
            'booking_id' => $booking->id, 'room_id' => $room->id,
            'reference' => 'REF-TEST-1', 'type' => 'refund',
            'gross_amount' => 2000, 'net_amount' => 2000,
            'payment_method' => 'flutterwave', 'channel' => 'app',
            'status' => 'confirmed', 'transaction_date' => now(),
        ]);
        $this->assertSame(0, $this->report()['outflow_cents']);

        $booking->update(['status' => 'refunded']);
        $this->assertSame(0, $this->report()['outflow_cents']);

        AccommodationLedger::where('reference', 'REF-TEST-1')->update(['status' => 'refunded']);
        $this->assertSame(200000, $this->report()['outflow_cents']);
        $this->assertSame(800000, $this->report()['net_cents']);
    }

    public function test_invalid_dates_and_unknown_source_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(IplFinancialStatement::class)->report('2026-10-27', '2026-10-26');
    }

    public function test_out_of_range_transactions_do_not_contribute_to_total(): void
    {
        $user = User::factory()->create();
        FoodOrder::create([
            'user_id' => $user->id, 'type' => 'pickup', 'total_amount' => 9000,
            'status' => 'paid', 'payment_method' => 'flutterwave', 'paid_at' => now()->subDays(20),
        ]);
        $this->assertSame(0, $this->report()['inflow_cents']);
    }
}
