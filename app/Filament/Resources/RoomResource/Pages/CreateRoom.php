<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    public function getHeading(): string | Htmlable
    {
        return 'Add New Suite';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Register a new luxury accommodation unit to the Insignia Courts inventory.';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Suite created successfully.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
