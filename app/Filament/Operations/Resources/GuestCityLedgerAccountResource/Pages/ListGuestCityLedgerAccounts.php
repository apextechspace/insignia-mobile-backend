<?php

namespace App\Filament\Operations\Resources\GuestCityLedgerAccountResource\Pages;

use App\Filament\Operations\Resources\GuestCityLedgerAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGuestCityLedgerAccounts extends ListRecords
{
    protected static string $resource = GuestCityLedgerAccountResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()->label('Open Prepaid Account')]; }
}
