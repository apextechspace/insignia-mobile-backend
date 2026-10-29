<?php

namespace App\Filament\Resources\ServiceBookingResource\Pages;

use App\Filament\Resources\ServiceBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceBooking extends CreateRecord
{
    protected static string $resource = ServiceBookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'pending';
        return $data;
    }
}
