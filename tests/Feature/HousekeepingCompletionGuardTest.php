<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\StaffTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HousekeepingCompletionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_turnaround_cannot_be_completed_with_any_unchecked_item(): void
    {
        $room = Room::create([
            'name' => 'Housekeeping Test Suite',
            'type' => 'Suite',
            'price_per_night' => 45000,
            'status' => 'cleaning',
        ]);
        $task = StaffTask::create([
            'room_id' => $room->id,
            'task_title' => 'Turnaround',
            'task_type' => 'room_turnaround',
            'status' => 'pending',
            'checklist_items' => [
                ['item' => 'Change linens', 'done' => true],
                ['item' => 'Inspect bathroom', 'done' => false],
            ],
        ]);
        try {
            $task->update(['status' => 'completed', 'completed_at' => now()]);
            $this->fail('Incomplete checklist must not complete the task.');
        } catch (ValidationException $exception) {
            $this->assertSame('pending', $task->fresh()->status);
        }

        $task->update([
            'checklist_items' => [
                ['item' => 'Change linens', 'done' => true],
                ['item' => 'Inspect bathroom', 'done' => true],
            ],
            'status' => 'completed',
        ]);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }
}
