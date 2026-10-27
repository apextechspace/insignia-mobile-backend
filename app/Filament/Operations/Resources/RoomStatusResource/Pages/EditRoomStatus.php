<?php

namespace App\Filament\Operations\Resources\RoomStatusResource\Pages;

use App\Filament\Operations\Resources\RoomStatusResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRoomStatus extends EditRecord
{
    protected static string $resource = RoomStatusResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $activeStay = \App\Models\Booking::where('room_id', $this->record->id)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->exists();

        if ($activeStay && ($data['status'] ?? $this->record->status) !== 'occupied') {
            throw ValidationException::withMessages([
                'status' => 'An active checked-in guest is in this room. Use the recorded Check Out action first.',
            ]);
        }

        // Occupied is controlled by check-in/out; housekeeping cannot create occupancy.
        if (($data['status'] ?? '') === 'occupied' && !$activeStay) {
            throw ValidationException::withMessages([
                'status' => 'Use Guest Arrivals & Departures to check a verified guest in.',
            ]);
        }

        return $data;
    }
}
