<?php

namespace App\Filament\Resources\GuestRegistrationResource\Pages;

use App\Filament\Resources\GuestRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGuestRegistration extends EditRecord
{
    protected static string $resource = GuestRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
