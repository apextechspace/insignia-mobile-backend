<?php

namespace App\Filament\Operations\Resources\IncidentResource\Pages;

use App\Filament\Operations\Resources\IncidentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateIncident extends CreateRecord
{
    protected static string $resource = IncidentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reported_by'] = Auth::id();
        if (empty($data['incident_code'])) {
            $data['incident_code'] = 'INC-' . date('Y') . '-' . strtoupper(Str::random(5));
        }
        return $data;
    }
}
