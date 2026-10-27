<?php

namespace App\Filament\Pages;

use App\Models\ManualBankAccount;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualBankSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationLabel = 'Bank Transfer Settings';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.manual-bank-settings';

    public string $bankName = '';
    public string $accountNumber = '';
    public string $accountName = '';
    public bool $active = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isIplAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $account = ManualBankAccount::current() ?: ManualBankAccount::query()->latest('id')->first();
        if ($account) {
            $this->bankName = $account->bank_name;
            $this->accountNumber = $account->account_number;
            $this->accountName = $account->account_name;
            $this->active = $account->is_active;
        }
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->validate([
            'bankName' => ['required', 'string', 'min:2', 'max:120'],
            'accountNumber' => ['required', 'regex:/^\d{10}$/D'],
            'accountName' => ['required', 'string', 'min:3', 'max:160'],
            'active' => ['boolean'],
        ]);

        DB::transaction(function () use ($data): void {
            // Lock a stable database row to serialize changes by multiple owners.
            \App\Models\User::query()->orderBy('id')->lockForUpdate()->first();
            ManualBankAccount::query()->where('is_active', true)->update(['is_active' => false]);

            ManualBankAccount::create([
                'bank_name' => trim($data['bankName']),
                'account_number' => $data['accountNumber'],
                'account_name' => trim($data['accountName']),
                'is_active' => $data['active'],
                'updated_by' => auth()->id(),
            ]);
        });

        \Filament\Notifications\Notification::make()
            ->title($this->active ? 'Bank transfer details activated' : 'Bank details saved, manual transfers disabled')
            ->success()->send();
    }
}