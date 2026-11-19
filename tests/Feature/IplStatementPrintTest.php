<?php

namespace Tests\Feature;

use App\Filament\Pages\IplMasterStatement;
use App\Models\FoodOrder;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IplStatementPrintTest extends TestCase
{
    use RefreshDatabase;

    private function filters(): array
    {
        return ['from' => today()->subDay()->toDateString(), 'to' => today()->addDay()->toDateString(), 'source' => 'all'];
    }

    public function test_print_is_owner_only_even_when_the_url_is_opened_directly(): void
    {
        $url = route('ipl-statement.print', $this->filters());
        $this->get($url)->assertForbidden();
        foreach ([User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS, User::ROLE_STAFF, User::ROLE_AUXILIARY, User::ROLE_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
        }
    }

    public function test_print_and_csv_use_the_same_filtered_transactions_without_the_sidebar(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        FoodOrder::create([
            'user_id' => $owner->id, 'type' => 'pickup', 'total_amount' => 1234.50,
            'status' => 'paid', 'payment_method' => 'flutterwave', 'paid_at' => now(), 'flutterwave_reference' => 'MATCHED-PRINT-REF',
        ]);
        FoodOrder::create([
            'user_id' => $owner->id, 'type' => 'pickup', 'total_amount' => 9000,
            'status' => 'pending', 'flutterwave_reference' => 'UNPAID-NOT-INCLUDED',
        ]);
        $filters = array_merge($this->filters(), ['source' => 'dining']);
        $response = $this->get(route('ipl-statement.print', $filters))->assertOk()
            ->assertSee('MATCHED-PRINT-REF')->assertSee('NGN 1,234.50')
            ->assertSee('Department: Dining')->assertDontSee('UNPAID-NOT-INCLUDED')
            ->assertDontSee('fi-sidebar')->assertDontSee('livewire.js');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(123450, $response->viewData('statement')['net_cents']);
        $download = Livewire::test(IplMasterStatement::class)
            ->set('fromDate', $filters['from'])->set('toDate', $filters['to'])->set('sourceFilter', 'dining')
            ->assertSeeHtml('target="_blank"')
            ->call('exportCsv')->assertFileDownloaded();
        $csv = base64_decode($download->effects['download']['content']);
        $lines = preg_split('/\r?\n/', trim($csv));
        $this->assertCount(2, $lines);
        $exported = str_getcsv($lines[1]);
        $this->assertSame('MATCHED-PRINT-REF', $exported[3]);
        $this->assertSame('1234.50', $exported[6]);
        $this->assertSame('1234.50', $exported[7]);
        $this->get(route('ipl-statement.print', array_merge($filters, ['source' => 'boutique'])))
            ->assertOk()->assertDontSee('MATCHED-PRINT-REF')->assertSee('No recorded transactions');
    }

    public function test_print_rejects_invalid_or_unbounded_filters(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->getJson(route('ipl-statement.print', ['from' => 'wrong', 'to' => 'wrong', 'source' => 'all']))->assertUnprocessable();
        $this->getJson(route('ipl-statement.print', ['from' => '2024-01-01', 'to' => '2026-11-18', 'source' => 'all']))->assertUnprocessable();
        $this->getJson(route('ipl-statement.print', array_merge($this->filters(), ['source' => 'unknown'])))->assertUnprocessable();
    }
}
