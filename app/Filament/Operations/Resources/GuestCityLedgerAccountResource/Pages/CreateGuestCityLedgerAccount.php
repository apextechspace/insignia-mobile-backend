<?php

namespace App\Filament\Operations\Resources\GuestCityLedgerAccountResource\Pages;

use App\Filament\Operations\Resources\GuestCityLedgerAccountResource;
use App\Services\GuestCityLedger;
use Filament\Resources\Pages\CreateRecord;

class CreateGuestCityLedgerAccount extends CreateRecord
{
    protected static string $resource = GuestCityLedgerAccountResource::class;
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(GuestCityLedger::class)->open($data, auth()->user());
    }
}
