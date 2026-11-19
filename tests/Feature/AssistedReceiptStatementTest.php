<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\MonthlyStatement;
use App\Models\Room;
use App\Models\User;
use App\Services\IplFinancialStatement;
use App\Services\MclStatementReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssistedReceiptStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_deposit_and_balance_are_posted_once_across_periods(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $room = Room::create(['name' => 'Room', 'type' => 'Standard Room', 'price_per_night' => 100000]);
        $stay = Booking::create(['reserved_by' => $manager->id, 'room_id' => $room->id,
            'check_in' => '2027-01-10', 'check_out' => '2027-01-13', 'total_price' => 300000,
            'status' => 'confirmed', 'minimum_payment_kobo' => 10000000]);
        foreach (['DEPOSIT' => ['2026-10-15 10:00:00', 10000000], 'BALANCE' => [null, 20000000]] as $reference => [$verifiedAt, $kobo]) {
            DB::table('assisted_reservation_payments')->insert(['booking_id' => $stay->id,
                'submitted_by' => $manager->id, 'verified_by' => $verifiedAt ? $manager->id : null,
                'amount_kobo' => $kobo, 'reference' => $reference, 'transaction_date' => '2026-10-15',
                'proof_path' => 'private-proof.png', 'verified_at' => $verifiedAt, 'method' => 'pos',
            ]);
        }
        $reports = app(IplFinancialStatement::class);
        $october = $reports->report('2026-10-01', '2026-10-31');
        $this->assertSame(10000000, $october['inflow_cents']);
        $this->assertCount(1, $october['rows']);
        DB::table('assisted_reservation_payments')->where('reference', 'BALANCE')->update(['verified_at' => '2026-11-02 10:00:00', 'verified_by' => $manager->id]);
        $stay->update(['paid_at' => '2026-11-02 10:00:00']);
        $this->assertSame(10000000, $reports->report('2026-10-01', '2026-10-31')['inflow_cents']);
        $this->assertSame(20000000, $reports->report('2026-11-01', '2026-11-30')['inflow_cents']);
        $this->assertSame(30000000, $reports->report('2026-10-01', '2026-11-30')['inflow_cents']);
        $monthly = new MonthlyStatement(['year' => 2026, 'month' => 10, 'management_fee_percentage' => 0]);
        $this->assertSame(10000000, app(MclStatementReport::class)->build($monthly)['receipts']);
        $this->assertSame(0, $reports->report('2026-10-01', '2026-11-30', 'dining')['inflow_cents']);
    }

    public function test_receipt_dates_use_nigeria_day_boundaries(): void
    {
        $user = User::factory()->create();
        \App\Models\FoodOrder::create(['user_id' => $user->id, 'type' => 'pickup', 'status' => 'paid', 'total_amount' => 1000,
            'paid_at' => '2026-11-18 23:30:00']);
        $report = app(IplFinancialStatement::class)->report('2026-11-19', '2026-11-19');
        $this->assertSame(100000, $report['inflow_cents']);
        $this->assertSame('2026-11-19 00:30:00', $report['rows']->first()['date']);
    }
}
