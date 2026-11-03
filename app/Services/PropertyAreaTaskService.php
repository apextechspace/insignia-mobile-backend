<?php

namespace App\Services;

use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyAreaTaskService
{
    public function verify(StaffTask $task, User $supervisor): void
    {
        // Same supervising roles as the existing Operations task assignment flow.
        if (! in_array($supervisor->role, [User::ROLE_ADMIN, User::ROLE_IPL_ADMIN, User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS], true)) {
            throw ValidationException::withMessages(['task' => 'Only an Operations supervisor can verify this duty.']);
        }
        DB::transaction(function () use ($task, $supervisor) {
            $locked = StaffTask::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if (! $locked->property_area_id || $locked->room_id || $locked->status !== 'completed' || ! $locked->completed_at) {
                throw ValidationException::withMessages(['task' => 'Complete the property-area duty before verifying it.']);
            }
            $locked->update(['status' => 'verified', 'verified_by' => $supervisor->id, 'verified_at' => now()]);
        }, 3);
    }
}
