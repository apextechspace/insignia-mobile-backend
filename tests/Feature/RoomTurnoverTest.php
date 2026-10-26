<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\RoomTurnoverService;
use App\Services\StayOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoomTurnoverTest extends TestCase
{
    use RefreshDatabase;

    private function checkedOutRoom(): array
    {
        $room = Room::create([
            'name' => 'Orchid Suite', 'type' => 'Suite',
            'price_per_night' => 65000, 'status' => 'available',
        ]);
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'total_price' => 130000,
            'status' => 'confirmed',
            'paid_at' => now(),
            'payment_method' => 'flutterwave',
        ]);
        $operator = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $service = app(StayOperationsService::class);
        $service->checkIn($booking, $operator);
        $service->checkOut($booking, $operator);
        return [$room->fresh(), $operator, $booking];
    }

    public function test_checkout_creates_a_new_room_turnaround_task(): void
    {
        [$room] = $this->checkedOutRoom();
        $task = StaffTask::where('room_id', $room->id)->where('task_type', 'room_turnaround')->firstOrFail();
        $this->assertSame('pending', $task->status);
        $this->assertCount(4, $task->checklist_items);
        $this->assertSame('cleaning', $room->status);
    }

    public function test_supervisor_cannot_release_room_before_cleaning_is_complete(): void
    {
        [$room, $operator] = $this->checkedOutRoom();
        $this->expectException(ValidationException::class);
        app(RoomTurnoverService::class)->markReady($room, $operator);
    }

    public function test_completed_cleaning_and_checked_checklist_releases_room(): void
    {
        [$room, $operator] = $this->checkedOutRoom();
        $task = StaffTask::where('room_id', $room->id)->firstOrFail();
        $items = array_map(fn (array $item) => array_merge($item, ['done' => true]), $task->checklist_items);
        $task->update(['status' => 'completed', 'completed_at' => now(), 'checklist_items' => $items]);

        app(RoomTurnoverService::class)->markReady($room, $operator);
        $this->assertSame('available', $room->fresh()->status);
        $this->assertSame('verified', $task->fresh()->status);
    }

    public function test_incomplete_checklist_cannot_release_room(): void
    {
        [$room, $operator] = $this->checkedOutRoom();
        StaffTask::where('room_id', $room->id)->firstOrFail()
            ->update(['status' => 'completed', 'completed_at' => now()]);
        $this->expectException(ValidationException::class);
        app(RoomTurnoverService::class)->markReady($room, $operator);
    }

    public function test_unprivileged_staff_cannot_verify_cleaning(): void
    {
        [$room] = $this->checkedOutRoom();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $this->expectException(ValidationException::class);
        app(RoomTurnoverService::class)->markReady($room, $staff);
    }
}
