<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\AccommodationLedgerResource;
use App\Filament\Operations\Resources\ExpenseResource;
use App\Models\AccommodationLedger;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcl_cannot_edit_accommodation_ledger(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $this->assertTrue(AccommodationLedgerResource::canViewAny());
        $this->assertFalse(AccommodationLedgerResource::canCreate());
        $this->assertFalse(AccommodationLedgerResource::canEdit(new AccommodationLedger()));
    }

    public function test_non_ipl_can_only_edit_own_pending_expense(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);
        $other = User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]);

        $mine = new Expense(['logged_by' => $staff->id, 'status' => 'pending_approval']);
        $theirs = new Expense(['logged_by' => $other->id, 'status' => 'pending_approval']);
        $approved = new Expense(['logged_by' => $staff->id, 'status' => 'approved']);

        $this->actingAs($staff);
        $this->assertTrue(ExpenseResource::canEdit($mine));
        $this->assertFalse(ExpenseResource::canEdit($theirs));
        $this->assertFalse(ExpenseResource::canView($theirs));
        $this->assertFalse(ExpenseResource::canEdit($approved));
        $this->assertFalse(ExpenseResource::canDelete($mine));

        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $this->actingAs($owner);
        $this->assertTrue(ExpenseResource::canView($theirs));
        $this->assertTrue(ExpenseResource::canEdit($approved));
    }
}
