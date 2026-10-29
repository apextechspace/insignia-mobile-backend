<?php

namespace Tests\Feature;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\ServiceBookingResource\Pages\EditServiceBooking;
use App\Models\Booking;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaidRecordEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
    }

    public function test_paid_room_dates_and_guest_cannot_be_edited_or_deleted(): void
    {
        $room = Room::create(['name' => 'Paid Suite', 'type' => 'Suite', 'price_per_night' => 65000, 'status' => 'available']);
        $booking = Booking::create([
            'user_id' => auth()->id(), 'room_id' => $room->id,
            'check_in' => today()->toDateString(), 'check_out' => today()->addDays(2)->toDateString(),
            'status' => 'confirmed', 'total_price' => 130000, 'paid_at' => now(),
        ]);
        $this->assertFalse(BookingResource::canEdit($booking));
        $this->assertFalse(BookingResource::canDelete($booking));
        $booking->update(['paid_at' => null, 'status' => 'pending']);
        PaymentAttempt::create([
            'gateway' => 'flutterwave', 'reference' => 'PENDING-EVIDENCE',
            'payable_type' => Booking::class, 'payable_id' => $booking->id,
            'amount' => 130000, 'currency' => 'NGN', 'status' => 'initiated',
        ]);
        $this->assertFalse(BookingResource::canDelete($booking->fresh()));
    }

    public function test_paid_boutique_amount_cannot_be_changed_in_owner_editor(): void
    {
        $product = Product::create(['name' => 'Paid item', 'category' => 'Decor', 'price' => 12000, 'stock' => 5]);
        $order = Order::create([
            'user_id' => auth()->id(), 'product_id' => $product->id, 'quantity' => 2,
            'total_price' => 24000, 'status' => 'confirmed', 'paid_at' => now(),
        ]);
        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->fillForm(['total_price' => 1])->call('save')->assertHasFormErrors(['total_price']);
        $this->assertEquals(24000, $order->fresh()->total_price);
    }

    public function test_paid_concierge_service_cannot_be_changed_in_owner_editor(): void
    {
        $service = Service::create(['name' => 'Original service', 'category' => 'Wellness', 'type' => 'booking', 'price' => 5000, 'is_active' => true]);
        $replacement = Service::create(['name' => 'Other service', 'category' => 'Wellness', 'type' => 'booking', 'price' => 9000, 'is_active' => true]);
        $booking = ServiceBooking::create([
            'user_id' => auth()->id(), 'service_id' => $service->id, 'total_price' => 5000,
            'status' => 'confirmed', 'paid_at' => now(),
        ]);
        Livewire::test(EditServiceBooking::class, ['record' => $booking->id])
            ->fillForm(['service_id' => $replacement->id])->call('save')->assertHasFormErrors(['service_id']);
        $this->assertSame($service->id, $booking->fresh()->service_id);
    }
}
