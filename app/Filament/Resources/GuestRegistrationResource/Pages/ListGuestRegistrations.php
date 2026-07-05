<?php

namespace App\Filament\Resources\GuestRegistrationResource\Pages;

use App\Filament\Resources\GuestRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGuestRegistrations extends ListRecords
{
    protected static string $resource = GuestRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
