<?php

namespace App\Filament\Auxiliary\Resources;

class ServiceBookingResource extends FulfilmentResource
{
    protected static ?string $model = \App\Models\ServiceBooking::class;

    protected static ?string $navigationLabel = 'Services & Coworking';

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    public static function transitions(): array
    {
        return ['confirmed' => 'in-progress', 'in-progress' => 'completed'];
    }

    public static function getPages(): array
    {
        return ['index' => ServiceBookingResource\Pages\ListServiceBookings::route('/')];
    }
}
