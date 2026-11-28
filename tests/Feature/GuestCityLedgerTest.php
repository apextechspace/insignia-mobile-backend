<?php

namespace Tests\Feature;

use App\Models\{AccommodationLedger, Booking, GuestCityLedgerEntry, Room, User};
use App\Services\GuestCityLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuestCityLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
    }

    private function booking(User $manager): Booking
    {
        $room = Room::create(['name' => 'Ledger Suite', 'type' => 'Suite', 'price_per_night' => 100000, 'status' => 'available']);
        return Booking::create([
            'reserved_by' => $manager->id, 'room_id' => $room->id,
            'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDays(2)->toDateString(),
            'total_price' => '100000.00', 'minimum_payment_kobo' => 5000000,
            'status' => 'pending', 'hold_expires_at' => now()->addMinutes(15),
        ]);
    }

    public function test_verified_prepaid_funds_can_be_applied_once_to_fully_settle_a_stay(): void
    {
        $manager = $this->manager();
        $ledger = app(GuestCityLedger::class);
        $account = $ledger->open(['guest_name' => 'Ada Guest', 'phone' => '08000000000'], $manager);
        $ledger->credit($account, $manager, 100000000, 'pos', 'CITY-1000000', 'city-ledger-evidence/receipt.pdf', 'Advance accommodation funds');
        $stay = $this->booking($manager);

        $debit = $ledger->applyToBooking($account, $stay, $manager, 'First stay');

        $this->assertSame(10000000, $debit->amount_kobo);
        $this->assertSame(90000000, GuestCityLedger::balance($account));
        $this->assertDatabaseHas('bookings', ['id' => $stay->id, 'status' => 'confirmed', 'payment_method' => 'city_ledger']);
        $this->assertNotNull($stay->fresh()->paid_at);
        $this->assertDatabaseCount('guest_city_ledger_entries', 2);
        $this->assertSame(1, AccommodationLedger::where('booking_id', $stay->id)->where('type', 'revenue')->count());

        try { $ledger->applyToBooking($account, $stay->fresh(), $manager); $this->fail('A paid booking was charged twice.'); }
        catch (ValidationException) { $this->assertSame(2, GuestCityLedgerEntry::count()); }
    }

    public function test_insufficient_balance_does_not_mark_or_charge_a_booking(): void
    {
        $manager = $this->manager();
        $ledger = app(GuestCityLedger::class);
        $account = $ledger->open(['guest_name' => 'Insufficient Guest'], $manager);
        $ledger->credit($account, $manager, 9999999, 'bank_transfer', 'CITY-LOW', 'city-ledger-evidence/low.pdf', null);
        $stay = $this->booking($manager);

        try { $ledger->applyToBooking($account, $stay, $manager); $this->fail('Insufficient balance was accepted.'); }
        catch (ValidationException) {
            $this->assertSame(9999999, GuestCityLedger::balance($account));
            $this->assertNull($stay->fresh()->paid_at);
            $this->assertSame(1, GuestCityLedgerEntry::count());
        }
    }

    public function test_same_payment_reference_cannot_be_credited_twice(): void
    {
        $manager = $this->manager(); $ledger = app(GuestCityLedger::class);
        $first = $ledger->open(['guest_name' => 'First'], $manager);
        $second = $ledger->open(['guest_name' => 'Second'], $manager);
        $ledger->credit($first, $manager, 10000, 'pos', 'DUPLICATE-CITY', 'city-ledger-evidence/one.pdf', null);

        $this->expectException(ValidationException::class);
        $ledger->credit($second, $manager, 10000, 'pos', 'duplicate-city', 'city-ledger-evidence/two.pdf', null);
    }
}
