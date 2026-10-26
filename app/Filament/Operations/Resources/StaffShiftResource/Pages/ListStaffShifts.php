<?php

namespace App\Filament\Operations\Resources\StaffShiftResource\Pages;

use App\Filament\Operations\Resources\StaffShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaffShifts extends ListRecords
{
    protected static string $resource = StaffShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Schedule New Shift'),
        ];
    }
}
