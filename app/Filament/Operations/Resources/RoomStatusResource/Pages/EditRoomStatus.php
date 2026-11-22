<?php

namespace App\Filament\Operations\Resources\RoomStatusResource\Pages;

use App\Filament\Operations\Resources\RoomStatusResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class EditRoomStatus extends EditRecord
{
    protected static string $resource = RoomStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('createReadinessChecklist')->label('Create Readiness Checklist')
            ->icon('heroicon-o-clipboard-document-check')
            ->authorize(fn () => RoomStatusResource::canEdit($this->record))
            ->visible(fn () => $this->record->status === 'cleaning' && !\App\Models\StaffTask::where('room_id', $this->record->id)->where('task_type', 'room_turnaround')->exists())
            ->requiresConfirmation()
            ->modalDescription('Create a fresh inspection checklist for this vacant room. Complete it under Tasks, then use Room Readiness Review → Verify & Release. This does not mark the room ready automatically.')
            ->action(function (): void {
                try {
                    app(\App\Services\RoomTurnoverService::class)->createMissingChecklist($this->record, auth()->user());
                    Notification::make()->success()->title('Readiness checklist created')->body('Open Tasks, complete the room inspection, then use Room Readiness Review → Verify & Release.')->persistent()->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Unable to create checklist')->body(collect($exception->errors())->flatten()->first())->send();
                }
            })];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Room status was not changed')
                ->body(collect($exception->errors())->flatten()->first().' For cleaning rooms, complete the checklist under Tasks, then use Room Readiness Review → Verify & Release.')
                ->persistent()->send();
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $key = str_starts_with($field, 'data.') ? $field : 'data.'.(in_array($field, ['room', 'checklist_items']) ? 'status' : $field);
                $errors[$key] = array_merge($errors[$key] ?? [], $messages);
            }
            throw ValidationException::withMessages($errors);
        }
    }

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
