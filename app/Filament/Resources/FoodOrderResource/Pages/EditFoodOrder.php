<?php

namespace App\Filament\Resources\FoodOrderResource\Pages;

use App\Filament\Resources\FoodOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFoodOrder extends EditRecord
{
    protected static string $resource = FoodOrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A manual edit must not turn an unpaid order into a fulfilled one.
        // Payment approval is performed exclusively by the verified gateway
        // or an authorized, audited manual payment action.
        if (empty($this->record->paid_at)
            && in_array($data['status'] ?? $this->record->status, ['paid','preparing','delivered'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Verify payment before progressing this transaction.',
            ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
