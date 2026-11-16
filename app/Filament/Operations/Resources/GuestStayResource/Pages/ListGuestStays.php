<?php

namespace App\Filament\Operations\Resources\GuestStayResource\Pages;

use App\Filament\Operations\Resources\GuestStayResource;
use App\Models\Booking;
use App\Models\Room;
use App\Services\RoomAvailability;
use App\Services\AssistedReservationPayments;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListGuestStays extends ListRecords
{
    protected static string $resource = GuestStayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newReservation')
                ->label('New Reservation')
                ->authorize(fn (): bool => GuestStayResource::canViewAny())
                ->modalSubmitActionLabel('Hold Room for 15 Minutes')
                ->form([
                    Components\Select::make('room_id')->label('Suite')->searchable()
                        ->options(fn () => Room::where('is_available', true)->where('status', '!=', 'maintenance')->orderBy('name')->pluck('name', 'id'))
                        ->required()->live(),
                    Components\DatePicker::make('check_in')->required()->format('Y-m-d')
                        ->minDate(fn () => today(config('property.timezone'))->toDateString())->live(),
                    Components\DatePicker::make('check_out')->required()->format('Y-m-d')->after('check_in')->live(),
                    Components\Placeholder::make('quote')->label('Quoted stay total')
                        ->content(function (Get $get): string {
                            $room = Room::find($get('room_id'));
                            $arrival = $get('check_in');
                            $departure = $get('check_out');
                            if (! $room || ! $arrival || ! $departure || $arrival >= $departure) {
                                return 'Select a suite and dates to see the total.';
                            }

                            return '₦'.number_format((float) RoomAvailability::quote($room, $arrival, $departure), 2);
                        }),
                    Components\Select::make('guests')->options([1 => '1 guest', 2 => '2 guests'])->default(1)->required(),
                    Components\TextInput::make('guest_name')->label('Lead guest name')->required()->maxLength(255),
                    Components\TextInput::make('guest_phone')->label('Lead guest phone')->tel()->required()->minLength(7)->maxLength(40),
                    Components\TextInput::make('guest_email')->label('Lead guest email')->email()->maxLength(255),
                    Components\Textarea::make('special_requests')->label('Special requests')->maxLength(2000),
                ])
                ->action(function (Action $action, array $data) {
                    $staff = auth()->user();
                    abort_unless(GuestStayResource::canViewAny(), 403);

                    try {
                        $booking = DB::transaction(function () use ($data, $staff): Booking {
                            $room = RoomAvailability::lockAvailableRoom((int) $data['room_id'], $data['check_in'], $data['check_out']);
                            $total = RoomAvailability::quote($room, $data['check_in'], $data['check_out']);

                            return Booking::create([
                                'reserved_by' => $staff->id,
                                'room_id' => $room->id,
                                'check_in' => $data['check_in'],
                                'check_out' => $data['check_out'],
                                'guests' => $data['guests'],
                                'total_price' => $total,
                                'minimum_payment_kobo' => AssistedReservationPayments::minimum($total),
                                'status' => 'pending',
                                'hold_expires_at' => now()->addMinutes(15),
                                'booking_contact' => [
                                    'name' => $data['guest_name'],
                                    'phone' => $data['guest_phone'],
                                    'email' => $data['guest_email'] ?? null,
                                ],
                                'special_requests' => $data['special_requests'] ?? null,
                            ]);
                        }, 3);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                        $action->halt();
                    }

                    Notification::make()->success()->title("Reservation #{$booking->id} held for 15 minutes")->send();

                    return redirect(GuestStayResource::getUrl('view', ['record' => $booking]));
                }),
        ];
    }
}
