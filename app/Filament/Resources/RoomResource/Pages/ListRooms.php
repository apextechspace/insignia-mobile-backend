<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    public function getTitle(): string
    {
        return 'Suites & Room Inventory';
    }

    public function getSubheading(): ?string
    {
        return 'Oversee Insignia Courts accommodation assets, live housekeeping readiness, nightly pricing, and public booking availability.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add New Suite')
                ->icon('heroicon-m-plus-circle'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RoomResource\Widgets\RoomOverviewWidget::class,
        ];
    }
}
