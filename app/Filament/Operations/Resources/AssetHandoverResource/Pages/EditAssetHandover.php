<?php

namespace App\Filament\Operations\Resources\AssetHandoverResource\Pages;

use App\Filament\Operations\Resources\AssetHandoverResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditAssetHandover extends EditRecord
{
    protected static string $resource = AssetHandoverResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $current = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            abort_unless(AssetHandoverResource::canEdit($current), 403);

            return parent::handleRecordUpdate($current, $data);
        });
    }
}
