<?php

namespace App\Filament\Operations\Resources\MonthlyStatementResource\Pages;

use App\Filament\Operations\Resources\MonthlyStatementResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditMonthlyStatement extends EditRecord
{
    protected static string $resource = MonthlyStatementResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $current = MonthlyStatementResource::getEloquentQuery()->lockForUpdate()->findOrFail($record->id);
            abort_unless(MonthlyStatementResource::canEdit($current), 403);

            return parent::handleRecordUpdate($current, $data);
        });
    }
}
