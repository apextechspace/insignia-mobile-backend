<?php

namespace Tests\Feature;

use App\Filament\Operations\Resources\ConsumableItemResource\Pages\ListConsumableItems;
use App\Filament\Operations\Widgets\OperationsDashboardWidget;
use App\Models\Booking;
use App\Models\ConsumableItem;
use App\Models\Room;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ConsumableStockService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardDataIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function item(): ConsumableItem
    {
        return ConsumableItem::create(['name' => 'Bath towel', 'category' => 'linen', 'unit' => 'pieces', 'current_stock' => 2, 'minimum_alert_level' => 1]);
    }

    public function test_inventory_issue_form_does_not_allow_stock_to_become_negative(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $room = Room::create(['name' => 'Stock room', 'type' => 'Suite', 'price_per_night' => 1000]);
        $item = $this->item();
        Livewire::test(ListConsumableItems::class)->callTableAction('issue_to_room', $item, ['room_id' => $room->id, 'quantity' => 3])
            ->assertHasTableActionErrors(['quantity']);
        $this->assertSame(2, $item->fresh()->current_stock);
        $this->assertDatabaseCount('stock_movements', 0);
        Livewire::test(ListConsumableItems::class)->callTableAction('issue_to_room', $item->fresh(), ['room_id' => $room->id, 'quantity' => 2])
            ->assertHasNoTableActionErrors();
        $this->assertSame(0, $item->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', ['consumable_item_id' => $item->id, 'quantity' => -2, 'room_id' => $room->id]);
    }

    public function test_stock_movement_and_restock_balance_are_consistent(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $item = $this->item();
        app(ConsumableStockService::class)->restock($item, $actor, ['quantity' => 3]);
        $this->assertSame(5, $item->fresh()->current_stock);
        $this->assertSame(3, StockMovement::where('consumable_item_id', $item->id)->sum('quantity'));
        $this->expectException(ValidationException::class);
        app(ConsumableStockService::class)->restock($item, $actor, ['quantity' => 1.5]);
    }

    public function test_expired_online_holds_do_not_clutter_dashboard_pending_bookings(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $this->actingAs($manager);
        $room = Room::create(['name' => 'Hold room', 'type' => 'Suite', 'price_per_night' => 1000]);
        $base = ['user_id' => $manager->id, 'room_id' => $room->id, 'check_in' => today()->addDay()->toDateString(), 'check_out' => today()->addDays(2)->toDateString(), 'total_price' => 1000, 'status' => 'pending'];
        $expired = Booking::create($base + ['hold_expires_at' => now()->subMinute()]);
        $active = Booking::create($base + ['hold_expires_at' => now()->addMinutes(10)]);
        $manual = Booking::create($base + ['payment_method' => 'bank_transfer']);
        $data = (new OperationsDashboardWidget)->getViewData();
        $this->assertFalse($data['pendingReservations']->contains('id', $expired->id));
        $this->assertTrue($data['pendingReservations']->contains('id', $active->id));
        $this->assertTrue($data['pendingReservations']->contains('id', $manual->id));
        $this->assertSame(2, Booking::actionablePending()->count());
        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_room_status_action_verifies_the_completed_checkout_checklist_before_release(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $room = Room::create(['name' => 'Clean room', 'type' => 'Suite', 'price_per_night' => 1000, 'status' => 'cleaning']);
        $task = \App\Models\StaffTask::create(['room_id' => $room->id, 'task_title' => 'Checkout clean', 'task_type' => 'room_turnaround', 'status' => 'completed',
            'checklist_items' => [['item' => 'Inspect room', 'done' => true]]]);
        Livewire::test(\App\Filament\Operations\Resources\RoomStatusResource\Pages\ListRoomStatuses::class)->callTableAction('mark_ready', $room);
        $this->assertSame('available', $room->fresh()->status);
        $this->assertSame('verified', $task->fresh()->status);
    }

    public function test_ordinary_staff_cannot_release_a_room_through_the_status_button(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]));
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $room = Room::create(['name' => 'Maintenance room', 'type' => 'Suite', 'price_per_night' => 1000, 'status' => 'maintenance']);
        Livewire::test(\App\Filament\Operations\Resources\RoomStatusResource\Pages\ListRoomStatuses::class)->assertTableActionHidden('mark_ready', $room);
    }

    public function test_maintenance_room_with_active_guest_cannot_be_released_even_by_direct_edit(): void
    {
        $room = Room::create(['name' => 'Occupied maintenance room', 'type' => 'Suite', 'price_per_night' => 1000, 'status' => 'maintenance']);
        $stay = Booking::create(['room_id' => $room->id, 'user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'paid_at' => now(),
            'check_in' => today()->toDateString(), 'check_out' => today()->addDay()->toDateString(), 'total_price' => 1000]);
        $stay->forceFill(['checked_in_at' => now()])->save();
        $this->expectException(ValidationException::class);
        $room->update(['status' => 'available']);
    }

    public function test_maintenance_flag_cannot_bypass_an_unfinished_checkout_checklist(): void
    {
        $room = Room::create(['name' => 'Repair and cleaning room', 'type' => 'Suite', 'price_per_night' => 1000, 'status' => 'maintenance']);
        \App\Models\StaffTask::create(['room_id' => $room->id, 'task_title' => 'Unfinished checkout clean', 'task_type' => 'room_turnaround', 'status' => 'pending',
            'checklist_items' => [['item' => 'Inspect room', 'done' => false]]]);
        $this->expectException(ValidationException::class);
        app(\App\Services\RoomTurnoverService::class)->markReady($room, User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
    }
}
