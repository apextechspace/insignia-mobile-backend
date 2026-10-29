<?php

namespace App\Filament\Resources\ServiceBookingResource\Pages;

use App\Filament\Resources\ServiceBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceBooking extends EditRecord
{
    protected static string $resource = ServiceBookingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A manual edit must not turn an unpaid order into a fulfilled one.
        // Payment approval is performed exclusively by the verified gateway
        // or an authorized, audited manual payment action.
        if (empty($this->record->paid_at)
            && in_array($data['status'] ?? $this->record->status, ['confirmed','in-progress','completed'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.status' => 'Verify payment before progressing this transaction.',
            ]);
        }

        if ($this->record->paid_at) {
            foreach (['user_id', 'service_id', 'service_variant_id', 'total_price'] as $field) {
                if (array_key_exists($field, $data) && (float) $data[$field] !== (float) $this->record->{$field}) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'data.' . $field => 'Paid service details cannot be changed. Use a separate adjustment or booking.',
                    ]);
                }
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
