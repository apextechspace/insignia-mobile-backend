<?php

namespace Tests\Feature;

use App\Filament\Operations\Pages\AccommodationStatement;
use App\Models\{Booking, Room, User, FoodOrder};
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerAccommodationStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_totals_print_and_csv_include_verified_room_receipts_only(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $room = Room::create(['name' => 'Evania', 'type' => 'Executive Room', 'price_per_night' => 120937.50]);
        $stay = Booking::create(['reserved_by' => $manager->id, 'room_id' => $room->id, 'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDays(2)->toDateString(), 'total_price' => 120937.50, 'status' => 'confirmed', 'paid_at' => now()]);
        foreach (['ROOM-DEPOSIT', 'ROOM-BALANCE', 'PENDING-PROOF'] as $reference) DB::table('assisted_reservation_payments')->insert(['booking_id' => $stay->id, 'submitted_by' => $manager->id, 'amount_kobo' => 6046875, 'reference' => $reference, 'transaction_date' => today()->toDateString(), 'proof_path' => 'proof.png', 'method' => 'pos', 'verified_at' => $reference === 'PENDING-PROOF' ? null : now()]);
        FoodOrder::create(['user_id' => $manager->id, 'type' => 'pickup', 'status' => 'paid', 'total_amount' => 9999, 'paid_at' => now(), 'flutterwave_reference' => 'SECRET-AUX-PAYMENT']);
        $page = Livewire::test(AccommodationStatement::class)->set('sourceFilter', 'dining')->assertSee('₦120,937.50')->assertSee('ROOM-DEPOSIT')->assertSee('ROOM-BALANCE')->assertDontSee('PENDING-PROOF')->assertDontSee('SECRET-AUX-PAYMENT');
        $page->call('exportCsv')->assertFileDownloaded();
        $csv = base64_decode($page->effects['download']['content']);
        $this->assertStringContainsString('ROOM-DEPOSIT', $csv);
        $this->assertStringNotContainsString('SECRET-AUX-PAYMENT', $csv);
        $url = route('accommodation-statement.print', ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString(), 'source' => 'dining']);
        $response = $this->get($url)->assertOk()->assertSee('Accommodation Receipts Statement')->assertSee('NGN 120,937.50')->assertDontSee('SECRET-AUX-PAYMENT')->assertDontSee('fi-sidebar');
        $this->assertSame(12093750, $response->viewData('statement')['inflow_cents']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(AccommodationStatement::getUrl(panel: 'operations'))->assertOk();
        Livewire::test(\App\Filament\Operations\Widgets\OperationsDashboardWidget::class)->assertSee('Verified Room Receipts')->assertSee('₦120,937.50');
    }

    public function test_receipts_report_and_print_deny_front_desk_cleaner_auxiliary_and_guests(): void
    {
        $url = route('accommodation-statement.print', ['from' => '2026-11-01', 'to' => '2026-11-19']);
        $this->get($url)->assertForbidden();
        foreach ([User::ROLE_GUEST_RELATIONS, User::ROLE_STAFF, User::ROLE_AUXILIARY, User::ROLE_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->assertFalse(AccommodationStatement::canAccess());
            $this->get($url)->assertForbidden();
        }
    }

    public function test_month_and_year_presets_and_invalid_print_dates(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-19 14:00', 'Africa/Lagos'));
        Livewire::test(AccommodationStatement::class)->call('setPeriod', 'last_year')->assertSet('fromDate', '2025-01-01')->assertSet('toDate', '2025-12-31')
            ->call('setPeriod', 'this_year')->assertSet('fromDate', '2026-01-01')->assertSet('toDate', '2026-11-19')
            ->call('setPeriod', 'last_month')->assertSet('fromDate', '2026-10-01')->assertSet('toDate', '2026-10-31');
        $this->getJson(route('accommodation-statement.print', ['from' => 'wrong', 'to' => 'wrong']))->assertUnprocessable();
    }
}
