<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\StaffTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoomReadinessInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_cannot_change_from_cleaning_to_available_without_supervisor_verified_task(): void
    {
        $room = Room::create([
            'name' => 'Orchid Suite',
            'type' => 'Suite',
            'price_per_night' => 55000,
            'status' => 'cleaning',
        ]);

        $task = StaffTask::create([
            'room_id' => $room->id,
            'task_title' => 'Checkout turnover',
            'task_type' => 'room_turnaround',
            'status' => 'completed',
            'completed_at' => now(),
            'checklist_items' => [['item' => 'Sanitize suite', 'done' => true]],
        ]);

        try {
            $room->update(['status' => 'available']);
            $this->fail('An unverified turnover must not release a room.');
        } catch (ValidationException $e) {
            $this->assertSame('cleaning', $room->fresh()->status);
        }

        $task->update(['status' => 'verified']);
        $room->update(['status' => 'available']);
        $this->assertSame('available', $room->fresh()->status);
    }
}
