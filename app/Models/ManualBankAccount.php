<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualBankAccount extends Model
{
    protected $fillable = ['bank_name', 'account_number', 'account_name', 'is_active', 'updated_by'];

    protected $casts = ['is_active' => 'boolean'];

    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first();
    }

    public static function isReady(): bool
    {
        $account = static::current();

        return $account !== null
            && filled(trim($account->bank_name))
            && filled(trim($account->account_name))
            && preg_match('/^\d{10}$/D', $account->account_number) === 1;
    }
}