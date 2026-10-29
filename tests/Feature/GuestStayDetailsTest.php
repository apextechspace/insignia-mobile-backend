<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\GuestStayResource;
use App\Filament\Operations\Resources\GuestStayResource\Pages\ViewGuestStay;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\StayOperationsService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class GuestStayDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_GUEST_RELATIONS]));
    }

    private function booking(): Booking
    {
        return Booking::create([
            'user_id' => User::factory()->create(['name' => 'Booking Contact', 'phone' => '+2348001112222'])->id,
            'room_id' => Room::create([
                'name' => 'Arrival Suite', 'type' => 'Suite', 'price_per_night' => 65000, 'status' => 'available',
            ])->id,
            'check_in' => today()->toDateString(), 'check_out' => today()->addDays(2)->toDateString(),
            'total_price' => 130000, 'status' => 'confirmed', 'paid_at' => now(),
            'payment_method' => 'flutterwave', 'flutterwave_reference' => 'TEST-ARRIVAL-42',
        ]);
    }

    private function registration(): array
    {
        return [
            'full_name' => 'Actual Arriving Guest', 'phone' => '+2348003334444',
            'adults' => 2, 'children' => 1, 'identity_verified' => true, 'notes' => 'Extra towels requested',
        ];
    }

    public function test_details_page_shows_contact_payment_and_arrival_guidance(): void
    {
        $booking = $this->booking();
        Livewire::test(ViewGuestStay::class, ['record' => $booking->id])
            ->assertSuccessful()->assertSee('Booking Contact')->assertSee('Arrival Suite')
            ->assertSee('Verified paid')->assertSee('TEST-ARRIVAL-42')->assertSee('Awaiting arrival')
            ->assertActionVisible('check_in')->assertActionHidden('check_out');
    }

    public function test_details_page_completes_registration_and_departure_with_audit_history(): void
    {
        $booking = $this->booking();
        $staffId = auth()->id();
        Livewire::test(ViewGuestStay::class, ['record' => $booking->id])
            ->callAction('check_in', $this->registration())
            ->assertHasNoActionErrors()->assertSee('In house')->assertSee('Actual Arriving Guest')
            ->assertSee('Extra towels requested')->assertActionHidden('check_in')->assertActionVisible('check_out')
            ->callAction('check_out')->assertHasNoActionErrors()
            ->assertSee('Checked out')->assertActionHidden('check_in')->assertActionHidden('check_out');

        $stay = $booking->fresh();
        $this->assertSame($staffId, $stay->checked_in_by);
        $this->assertSame($staffId, $stay->checked_out_by);
        $this->assertSame('Booking Contact', $stay->user->name);
        $this->assertSame(2, (int) $stay->guest_registration['adults']);
        $this->assertSame('cleaning', $stay->room->status);
        $this->assertDatabaseHas('staff_tasks', ['room_id' => $stay->room_id, 'task_type' => 'room_turnaround']);
        $this->assertArrayNotHasKey('guest_registration', $stay->toArray());
    }

    public function test_identity_verification_is_required_in_the_form(): void
    {
        $booking = $this->booking();
        $data = array_replace($this->registration(), ['identity_verified' => false]);
        Livewire::test(ViewGuestStay::class, ['record' => $booking->id])
            ->callAction('check_in', $data)->assertHasActionErrors(['identity_verified']);
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->assertSame('available', $booking->room->fresh()->status);
    }

    public function test_missing_registration_is_rejected_by_the_service_without_mutation(): void
    {
        $booking = $this->booking();
        try {
            app(StayOperationsService::class)->checkIn($booking, auth()->user());
            $this->fail('Expected guest registration validation');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('full_name', $exception->errors());
            $this->assertArrayHasKey('identity_verified', $exception->errors());
        }
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->assertNull($booking->fresh()->guest_registration);
        $this->assertSame('available', $booking->room->fresh()->status);
    }

    public function test_invalid_party_size_and_unverified_identity_cannot_bypass_the_service(): void
    {
        $booking = $this->booking();
        try {
            app(StayOperationsService::class)->checkIn($booking, auth()->user(), array_replace(
                $this->registration(), ['adults' => 0, 'children' => -1, 'identity_verified' => false]
            ));
            $this->fail('Expected registration validation');
        } catch (ValidationException $exception) {
            foreach (['adults', 'children', 'identity_verified'] as $field) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        $this->assertNull($booking->fresh()->checked_in_by);
    }

    public function test_room_readiness_failure_does_not_save_registration(): void
    {
        $booking = $this->booking();
        $booking->room->update(['status' => 'maintenance']);
        Livewire::test(ViewGuestStay::class, ['record' => $booking->id])
            ->callAction('check_in', $this->registration());
        Notification::assertNotified('Check-in blocked');
        $this->assertNull($booking->fresh()->guest_registration);
        $this->assertSame('maintenance', $booking->room->fresh()->status);
    }

    public function test_general_staff_and_guests_cannot_open_guest_details_directly(): void
    {
        $booking = $this->booking();
        foreach ([User::ROLE_STAFF, User::ROLE_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->assertFalse(GuestStayResource::canView($booking));
            Livewire::test(ViewGuestStay::class, ['record' => $booking->id])->assertForbidden();
        }
    }
}
