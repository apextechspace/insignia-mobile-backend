<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentReconciliationCaseResource;
use App\Models\PaymentReconciliationCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReconciliationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_ipl_admin_can_view_all_payment_exceptions(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $this->assertTrue(PaymentReconciliationCaseResource::canViewAny());
        $this->assertFalse(PaymentReconciliationCaseResource::canCreate());

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $this->assertFalse(PaymentReconciliationCaseResource::canViewAny());

        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]));
        $this->assertFalse(PaymentReconciliationCaseResource::canViewAny());
    }

    public function test_payment_exception_records_cannot_be_mutated_through_resource(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $case = PaymentReconciliationCase::create([
            'gateway' => 'flutterwave',
            'payment_reference' => 'TEST-FLW-EXCEPTION',
            'provider_transaction_id' => '54241',
            'payable_type' => \App\Models\Booking::class,
            'payable_id' => 1,
            'reason' => 'room_date_conflict',
            'status' => 'needs_review',
        ]);

        $this->assertTrue(PaymentReconciliationCaseResource::canView($case));
        $this->assertFalse(PaymentReconciliationCaseResource::canEdit($case));
        $this->assertFalse(PaymentReconciliationCaseResource::canDelete($case));
        $this->assertFalse(PaymentReconciliationCaseResource::canDeleteAny());
    }
}
