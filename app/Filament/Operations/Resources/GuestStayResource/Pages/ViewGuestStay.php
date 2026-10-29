<?php

namespace App\Filament\Operations\Resources\GuestStayResource\Pages;

use App\Filament\Operations\Resources\GuestStayResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewGuestStay extends ViewRecord
{
    protected static string $resource = GuestStayResource::class;

    public function getTitle(): string
    {
        return 'Guest details · Booking #' . $this->record->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check_in')->label('Check In')->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('success')->modalHeading('Register guest and check in')
                ->modalSubmitActionLabel('Complete check-in')
                ->form(GuestStayResource::registrationForm())
                ->fillForm(fn () => GuestStayResource::registrationDefaults($this->record))
                ->visible(fn () => GuestStayResource::canCheckIn($this->record))
                ->action(function (array $data): void {
                    GuestStayResource::processStayAction($this->record, 'checkIn', $data);
                    $this->record->refresh();
                }),
            Action::make('check_out')->label('Check Out')->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('warning')->requiresConfirmation()
                ->modalDescription('Collect keys and confirm the guest has departed. This completes the stay and creates the housekeeping checklist.')
                ->visible(fn () => GuestStayResource::canCheckOut($this->record))
                ->action(function (): void {
                    GuestStayResource::processStayAction($this->record, 'checkOut');
                    $this->record->refresh();
                }),
        ];
    }
}
