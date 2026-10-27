<?php

namespace App\Filament\Operations\Resources\StaffTaskResource\Pages;

use App\Filament\Operations\Resources\StaffTaskResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditStaffTask extends EditRecord
{
    protected static string $resource = StaffTaskResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (Auth::user()?->isStaff()) {
            if ((int) $this->record->user_id !== (int) Auth::id()) {
                throw ValidationException::withMessages(['user_id' => 'This task is not assigned to you.']);
            }

            // Ignore forged Livewire fields; ordinary staff may submit only
            // checklist progress and notes for their own assigned tasks.
            return array_intersect_key($data, array_flip(['checklist_items', 'notes']));
        }

        if (($data['status'] ?? null) === 'verified') {
            throw ValidationException::withMessages(['status' => 'Verification belongs to the supervised room readiness process.']);
        }

        return $data;
    }
}
