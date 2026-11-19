<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                $room = \App\Services\RoomAvailability::lockAvailableRoom((int) $data['room_id'], $data['check_in'], $data['check_out']);
                return \App\Models\Booking::create(array_merge($data, [
                    'total_price' => \App\Services\RoomAvailability::quote($room, $data['check_in'], $data['check_out']),
                    'status' => 'pending', 'hold_expires_at' => now()->addMinutes(15),
                ]));
            }, 3);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all()
            );
        }
    }
}
