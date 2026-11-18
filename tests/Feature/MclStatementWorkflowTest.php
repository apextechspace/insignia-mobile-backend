<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\MonthlyStatementResource;
use App\Filament\Operations\Resources\MonthlyStatementResource\Pages\ListMonthlyStatements;
use App\Models\AccommodationLedger;
use App\Models\Expense;
use App\Models\MonthlyStatement;
use App\Models\PettyCashTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MclStatementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_resubmits_own_statement_with_a_reconciled_snapshot(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $statement = MonthlyStatement::create([
            'statement_code' => 'STM-2026-10', 'year' => 2026, 'month' => 10,
            'submitted_by' => $manager->id, 'status' => 'queried', 'actual_float_balance' => 300,
        ]);
        AccommodationLedger::create([
            'reference' => 'TEST-RECEIPT', 'gross_amount' => 1000, 'net_amount' => 1000,
            'type' => 'revenue', 'status' => 'confirmed', 'transaction_date' => '2026-10-05 09:00:00',
        ]);
        $expense = Expense::create([
            'title' => 'Repair', 'category' => 'maintenance_repairs', 'amount' => 200,
            'status' => 'approved', 'logged_by' => $manager->id, 'expense_date' => '2026-10-05 09:00:00',
        ]);
        foreach (['replenishment' => 500, 'disbursement' => 200] as $type => $amount) {
            PettyCashTransaction::create([
                'type' => $type, 'amount' => $amount, 'purpose' => $type, 'handled_by' => $manager->id,
                'expense_id' => $type === 'disbursement' ? $expense->id : null,
                'transaction_date' => '2026-10-05 10:00:00',
            ]);
        }
        foreach (['auxiliary', null] as $stream) {
            Expense::create([
                'title' => 'Non-accommodation expense', 'financial_stream' => $stream,
                'category' => 'other', 'amount' => 9000, 'status' => 'approved',
                'logged_by' => $manager->id, 'expense_date' => '2026-10-05 09:00:00',
            ]);
        }
        Livewire::test(ListMonthlyStatements::class)->callTableAction('submit_to_ipl', $statement);
        $statement->refresh();
        $this->assertSame('submitted_to_ipl', $statement->status);
        $this->assertSame('800.00', $statement->net_payable_to_ipl);
        $this->assertSame(0, $statement->snapshot['difference']);
        $this->get(MonthlyStatementResource::getUrl('view', ['record' => $statement], panel: 'operations'))
            ->assertOk()->assertSee('Imprest balance trend')->assertSee('TEST-RECEIPT');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $this->get(MonthlyStatementResource::getUrl('view', ['record' => $statement], panel: 'operations'))->assertNotFound();

        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $this->actingAs($owner);
        Livewire::test(ListMonthlyStatements::class)->callTableAction('approve', $statement);
        $this->assertSame('approved_by_ipl', $statement->fresh()->status);
        $this->assertSame($owner->id, $statement->fresh()->approved_by);
        Livewire::test(ListMonthlyStatements::class)->callTableAction('settle', $statement->fresh());
        $this->assertSame('settled', $statement->fresh()->status);
    }
}
