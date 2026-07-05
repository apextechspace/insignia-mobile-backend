<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Reservation Summary')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Guest Name')
                                    ->weight(FontWeight::Bold)
                                    ->icon('heroicon-m-user'),
                                Infolists\Components\TextEntry::make('room.name')
                                    ->label('Suite / Room')
                                    ->icon('heroicon-m-home'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('check_in')
                                    ->label('Check-In')
                                    ->date('D, M j, Y'),
                                Infolists\Components\TextEntry::make('check_out')
                                    ->label('Check-Out')
                                    ->date('D, M j, Y'),
                                Infolists\Components\TextEntry::make('nights')
                                    ->label('Duration')
                                    ->state(fn ($record) => $record->check_in && $record->check_out
                                        ? \Carbon\Carbon::parse($record->check_in)->diffInDays(\Carbon\Carbon::parse($record->check_out)) . ' nights'
                                        : '-'),
                                Infolists\Components\TextEntry::make('total_price')
                                    ->label('Total Charged')
                                    ->money('NGN')
                                    ->weight(FontWeight::Bold),
                            ]),
                    ]),

                Infolists\Components\Section::make('Payment Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('payment_reference')
                                    ->label('Payment Reference')
                                    ->copyable()
                                    ->placeholder('No reference recorded'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Booking Date')
                                    ->dateTime(),
                            ]),
                        Infolists\Components\ImageEntry::make('receipt_url')
                            ->label('Payment Proof')
                            ->disk('public')
                            ->height(300)
                            ->visible(fn ($record) => !empty($record->receipt_url)),
                    ]),
            ]);
    }
}
