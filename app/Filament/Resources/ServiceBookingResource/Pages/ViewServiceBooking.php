<?php

namespace App\Filament\Resources\ServiceBookingResource\Pages;

use App\Filament\Resources\ServiceBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceBooking extends ViewRecord
{
    protected static string $resource = ServiceBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
