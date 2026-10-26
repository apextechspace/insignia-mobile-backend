<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\GuestStayResource\Pages;
use App\Models\Booking;
use App\Models\User;
use App\Services\StayOperationsService;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class GuestStayResource extends Resource
{
    protected static ?string $model = Booking::class;
    protected static ?string $navigationLabel = 'Guest Arrivals & Departures';
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Operations';

    public static function canViewAny(): bool
    {
        return in_array(Auth::user()?->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS,
        ], true);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['confirmed', 'paid', 'completed']))
            ->defaultSort('check_in', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Booking #')->sortable(),
                Tables\Columns\TextColumn::make('room.name')->label('Suite')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Guest')->searchable(),
                Tables\Columns\TextColumn::make('check_in')->date()->sortable(),
                Tables\Columns\TextColumn::make('check_out')->date()->sortable(),
                Tables\Columns\TextColumn::make('checked_in_at')->label('Actual arrival')->dateTime()->placeholder('Not arrived'),
                Tables\Columns\TextColumn::make('checked_out_at')->label('Actual departure')->dateTime()->placeholder('Not departed'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('check_in')
                    ->label('Check In')->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')->requiresConfirmation()
                    ->visible(fn (Booking $record) => $record->paid_at
                        && !$record->checked_in_at && !$record->checked_out_at
                        && in_array($record->status, ['confirmed','paid'], true))
                    ->action(fn (Booking $record) => app(StayOperationsService::class)->checkIn($record, Auth::user())),
                Tables\Actions\Action::make('check_out')
                    ->label('Check Out')->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('warning')->requiresConfirmation()
                    ->visible(fn (Booking $record) => $record->checked_in_at && !$record->checked_out_at)
                    ->action(fn (Booking $record) => app(StayOperationsService::class)->checkOut($record, Auth::user())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGuestStays::route('/')];
    }
}
