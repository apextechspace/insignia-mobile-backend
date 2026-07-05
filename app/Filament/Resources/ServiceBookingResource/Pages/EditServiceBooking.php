<?php

namespace App\Filament\Resources\ServiceBookingResource\Pages;

use App\Filament\Resources\ServiceBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceBooking extends EditRecord
{
    protected static string $resource = ServiceBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
