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
            if (array_key_exists('checklist_items', $data)) {
                $original = array_values($this->record->checklist_items ?? []);
                $submitted = array_values($data['checklist_items'] ?? []);
                if (count($original) !== count($submitted)
                    || array_column($original, 'item') !== array_column($submitted, 'item')) {
                    throw ValidationException::withMessages(['data.checklist_items' => 'Staff can tick assigned checklist items, but cannot replace or remove them.']);
                }
            }

            return array_intersect_key($data, array_flip(['checklist_items', 'notes']));
        }

        if (($data['status'] ?? null) === 'verified') {
            throw ValidationException::withMessages(['status' => 'Verification belongs to the supervised room readiness process.']);
        }

        return $data;
    }
}
