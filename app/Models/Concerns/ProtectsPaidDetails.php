<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait ProtectsPaidDetails
{
    protected static function bootProtectsPaidDetails(): void
    {
        static::updating(function (Model $record): void {
            if (!$record->getRawOriginal('paid_at')) return;
            foreach ($record->paidDetailFields as $field) {
                if ($record->isDirty($field)) {
                    throw ValidationException::withMessages([
                        'data.' . $field => 'Paid transaction details cannot be changed. Use a separate adjustment or reservation.',
                    ]);
                }
            }
        });
    }
}
