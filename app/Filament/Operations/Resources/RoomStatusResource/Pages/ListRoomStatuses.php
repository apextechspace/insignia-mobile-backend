<?php

namespace App\Filament\Operations\Resources\RoomStatusResource\Pages;

use App\Filament\Operations\Resources\RoomStatusResource;
use Filament\Resources\Pages\ListRecords;

class ListRoomStatuses extends ListRecords
{
    protected static string $resource = RoomStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
