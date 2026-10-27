<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\DailyReconciliationResource;
use App\Filament\Operations\Resources\MonthlyStatementResource;
use App\Filament\Operations\Resources\PettyCashResource;
use App\Models\DailyReconciliation;
use App\Models\MonthlyStatement;
use App\Models\PettyCashTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceResourceRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_operating_float_can_be_posted_only_by_ipl(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $this->assertTrue(PettyCashResource::canViewAny());
        $this->assertFalse(PettyCashResource::canCreate());
        $this->assertFalse(PettyCashResource::canEdit(new PettyCashTransaction()));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $this->assertTrue(PettyCashResource::canCreate());
    }

    public function test_only_submitter_can_edit_submitted_daily_reconciliation(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $other = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $record = new DailyReconciliation(['submitted_by' => $operator->id, 'status' => 'submitted']);

        $this->actingAs($operator);
        $this->assertTrue(DailyReconciliationResource::canEdit($record));
        $this->actingAs($other);
        $this->assertFalse(DailyReconciliationResource::canEdit($record));

        $record->status = 'approved_by_ipl';
        $this->actingAs($operator);
        $this->assertFalse(DailyReconciliationResource::canEdit($record));
    }

    public function test_mcl_can_edit_draft_but_not_approved_statement(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $statement = new MonthlyStatement(['status' => 'draft']);
        $this->actingAs($operator);
        $this->assertTrue(MonthlyStatementResource::canEdit($statement));

        $statement->status = 'approved_by_ipl';
        $this->assertFalse(MonthlyStatementResource::canEdit($statement));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $this->assertTrue(MonthlyStatementResource::canEdit($statement));
        $statement->status = 'settled';
        $this->assertFalse(MonthlyStatementResource::canEdit($statement));
    }
}
