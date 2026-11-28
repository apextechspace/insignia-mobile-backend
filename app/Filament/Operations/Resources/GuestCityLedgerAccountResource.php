<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\GuestCityLedgerAccountResource\Pages;
use App\Models\GuestCityLedgerAccount;
use App\Models\User;
use App\Services\GuestCityLedger;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class GuestCityLedgerAccountResource extends Resource
{
    protected static ?string $model = GuestCityLedgerAccount::class;
    protected static ?string $navigationIcon = 'heroicon-o-wallet';
    protected static ?string $navigationLabel = 'Guest City Ledger';
    protected static ?string $modelLabel = 'City Ledger Account';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool { return GuestCityLedger::canManage(Auth::user()); }
    public static function canCreate(): bool { return self::canViewAny(); }
    public static function canView($record): bool { return self::canViewAny(); }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Guest prepaid account')->description('Use one account per guest or company. The balance can only be changed through a documented credit or a room charge.')->schema([
                Forms\Components\Select::make('user_id')->label('Linked app guest account (optional)')->relationship('user', 'name')->searchable(),
                Forms\Components\TextInput::make('guest_name')->label('Guest or company name')->required()->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(40),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\Textarea::make('notes')->label('Opening notes')->maxLength(2000)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('account_reference')->label('Account')->searchable()->copyable()->weight('bold'),
            Tables\Columns\TextColumn::make('guest_name')->label('Guest / company')->searchable(),
            Tables\Columns\TextColumn::make('phone')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('city_balance')->label('Available balance')->money('NGN')->state(fn (GuestCityLedgerAccount $record) => GuestCityLedger::balance($record) / 100),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('created_at')->label('Opened')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])->defaultSort('created_at', 'desc')->actions([Tables\Actions\ViewAction::make()->label('Open ledger')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGuestCityLedgerAccounts::route('/'),
            'create' => Pages\CreateGuestCityLedgerAccount::route('/create'),
            'view' => Pages\ViewGuestCityLedgerAccount::route('/{record}'),
        ];
    }
}
