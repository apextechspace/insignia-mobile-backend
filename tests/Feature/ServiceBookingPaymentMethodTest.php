<?php

namespace Tests\Feature;

use App\Actions\CreateServiceBookingAction;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceBookingPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_booking_defaults_to_flutterwave_when_no_receipt_provided(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $service = Service::create([
            'name' => 'Airport VIP Pickup',
            'category' => 'Transport',
            'type' => 'booking',
            'description' => 'Luxury chauffeur service',
            'price' => 35000,
            'is_active' => true,
            'requires_variant' => false,
        ]);

        $response = $this->postJson('/api/service-bookings', [
            'service_id' => $service->id,
            'notes' => 'No special requests',
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            'payment_reference' => 'SRV-AIR-6216',
            'flight_number' => 'BA075',
            'terminal' => 'Terminal 1',
            'pickup_location' => 'Murtala Muhammed Airport',
            'destination' => 'Insignia Courts',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('service_bookings', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'payment_method' => 'flutterwave',
            'status' => 'pending',
            'total_price' => 35000.00,
        ]);
    }

    public function test_service_booking_persists_explicit_payment_method(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $service = Service::create([
            'name' => 'Spa Treatment',
            'category' => 'Wellness',
            'type' => 'booking',
            'description' => 'Full body relaxation',
            'price' => 20000,
            'is_active' => true,
            'requires_variant' => false,
        ]);

        $response = $this->postJson('/api/service-bookings', [
            'service_id' => $service->id,
            'payment_method' => 'flutterwave',
            'payment_reference' => 'SRV-SPA-1234',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('service_bookings', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'payment_method' => 'flutterwave',
        ]);
    }

    public function test_action_defaults_safely_when_payment_method_is_omitted(): void
    {
        $user = User::factory()->create();

        $service = Service::create([
            'name' => 'Laundry Express',
            'category' => 'Housekeeping',
            'type' => 'booking',
            'description' => 'Same-day laundry service',
            'price' => 5000,
            'is_active' => true,
            'requires_variant' => false,
        ]);

        $action = new CreateServiceBookingAction();
        $booking = $action->execute([
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);

        $this->assertNotNull($booking->payment_method);
        $this->assertSame('flutterwave', $booking->payment_method);
    }
}
