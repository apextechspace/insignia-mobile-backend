<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An occupied suite is never released by a generic room-status edit.
 * The room lock serializes staff turnover with booking check-in/check-out.
 */
class RoomTurnoverService
{
    public function createForCheckout(Room $room): StaffTask
    {
        return StaffTask::create([
            'room_id' => $room->id,
            'task_title' => 'Guest checkout turnaround – ' . $room->name,
            'task_type' => 'room_turnaround',
            'priority' => 'high',
            'status' => 'pending',
            'checklist_items' => [
                ['item' => 'Replace bedsheets and towels', 'done' => false],
                ['item' => 'Clean bathroom and sanitize surfaces', 'done' => false],
                ['item' => 'Restock amenities and inspect fixtures', 'done' => false],
                ['item' => 'Inspect room for guest readiness', 'done' => false],
            ],
        ]);
    }

    public function markReady(Room $room, User $reviewer): void
    {
        if (!in_array($reviewer->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS,
        ], true)) {
            throw ValidationException::withMessages(['room' => 'Only authorized supervisors may release a cleaned suite.']);
        }

        DB::transaction(function () use ($room) {
            $lockedRoom = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($lockedRoom->status !== 'cleaning') {
                throw ValidationException::withMessages(['room' => 'Only cleaned suites may be marked ready.']);
            }

            if (Booking::where('room_id', $room->id)
                ->whereNotNull('checked_in_at')->whereNull('checked_out_at')->exists()) {
                throw ValidationException::withMessages(['room' => 'An active guest is still checked into this suite.']);
            }

            $task = StaffTask::where('room_id', $room->id)
                ->where('task_type', 'room_turnaround')
                ->latest('id')->lockForUpdate()->first();

            if (!$task || $task->status !== 'completed' || !$task->completed_at) {
                throw ValidationException::withMessages(['room' => 'Finish the latest checkout cleaning task before marking this suite ready.']);
            }

            $items = $task->checklist_items;
            if (!is_array($items) || count($items) === 0) {
                throw ValidationException::withMessages(['room' => 'A completed inspection checklist is required.']);
            }

            foreach ($items as $item) {
                if (empty($item['item']) || ($item['done'] ?? false) !== true) {
                    throw ValidationException::withMessages(['room' => 'All room turnaround checklist items must be checked.']);
                }
            }

            $task->update(['status' => 'verified']);
            $lockedRoom->update(['status' => 'available']);
        }, 3);
    }
}
