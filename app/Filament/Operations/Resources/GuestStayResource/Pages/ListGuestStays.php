<?php

namespace App\Filament\Operations\Resources\GuestStayResource\Pages;

use App\Filament\Operations\Resources\GuestStayResource;
use App\Models\Booking;
use App\Models\Room;
use App\Services\AssistedReservationPayments;
use App\Services\RoomAvailability;
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
            Action::make('recordExternalReservation')->label('Record External Booking')->icon('heroicon-o-globe-alt')
                ->authorize(fn () => GuestStayResource::canViewAny())
                ->modalHeading('Record a confirmed external reservation')
                ->modalDescription('One room per entry. Check the original channel confirmation first. This blocks dates locally only: update the channel calendar manually for direct bookings, changes and cancellations. Do not enter payment card details.')
                ->form([
                    Components\Section::make('Channel confirmation')->schema([
                        Components\Select::make('source')->label('Booked through')->options(\App\Services\ExternalReservations::SOURCES)->default('booking_com')->required()->live(),
                        Components\TextInput::make('other_source_name')->label('Other channel name')->maxLength(30)->required(fn (Get $get) => $get('source') === 'other')->visible(fn (Get $get) => $get('source') === 'other'),
                        Components\TextInput::make('reference')->label('Channel reservation reference')->required()->maxLength(120),
                        Components\TextInput::make('unit_reference')->label('Room-line reference')->default('1')->required()->maxLength(40)
                            ->helperText('For several rooms under one channel reference, record each room separately with a different room-line reference (1, 2, etc.).'),
                    ])->columns(['default' => 1, 'md' => 2]),
                    Components\Section::make('Room and agreed stay')->schema([
                        Components\Select::make('room_id')->label('Physical room / suite')->options(fn () => Room::where('is_available', true)->where('status', '!=', 'maintenance')->pluck('name', 'id'))->searchable()->required(),
                        Components\DatePicker::make('check_in')->required()->format('Y-m-d'),
                        Components\DatePicker::make('check_out')->required()->format('Y-m-d')->after('check_in'),
                        Components\TextInput::make('total')->label('Agreed total for this room (NGN)')->numeric()->minValue(0.01)->maxValue(99999999.99)->required()->helperText('Use this room line’s confirmed amount including taxes. Do not repeat the whole multi-room total, substitute our direct rate or deduct channel commission.'),
                        Components\Select::make('guests')->options([1 => '1 guest', 2 => '2 guests'])->default(1)->required(),
                    ])->columns(['default' => 1, 'md' => 2]),
                    Components\Section::make('Guest and payment arrangement')->schema([
                        Components\TextInput::make('guest_name')->label('Lead guest name')->required()->maxLength(255),
                        Components\TextInput::make('guest_phone')->label('Guest phone (if provided)')->tel()->maxLength(40),
                        Components\TextInput::make('guest_email')->label('Guest email (if provided)')->email()->maxLength(255),
                        Components\Select::make('payment_arrangement')->options(\App\Services\ExternalReservations::ARRANGEMENTS)->default('review_required')->required(),
                        Components\Textarea::make('channel_terms')->label('Original channel payment and cancellation terms')->required()->maxLength(4000)->columnSpanFull(),
                        Components\Checkbox::make('confirmation_checked')->label('I checked the channel reference, room, dates, amount and terms against the confirmed reservation')->accepted()->required()->columnSpanFull(),
                    ])->columns(['default' => 1, 'md' => 2]),
                ])
                ->action(function (Action $action, array $data) {
                    try { $stay = app(\App\Services\ExternalReservations::class)->create($data, auth()->user()); }
                    catch (ValidationException $exception) { Notification::make()->danger()->title($exception->getMessage())->send(); $action->halt(); }
                    Notification::make()->success()->title('External reservation recorded. Dates blocked locally.')->body('Keep availability and changes updated on the external channel manually.')->send();
                    return redirect(GuestStayResource::getUrl('view', ['record' => $stay]));
                }),
            Action::make('depositSettings')->label('Deposit Settings')
                ->visible(fn () => auth()->user()?->isIplAdmin())
                ->url(fn () => \App\Filament\Pages\ManualBankSettings::getUrl(panel: 'admin')),
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

                            $total = RoomAvailability::quote($room, $arrival, $departure);

                            return '₦'.number_format((float) $total, 2).' · '.\App\Services\ReservationTerms::summary(\App\Services\ReservationTerms::snapshot($arrival, $departure, $total, true));
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

                            $booking = Booking::create([
                                'reserved_by' => $staff->id,
                                'room_id' => $room->id,
                                'check_in' => $data['check_in'],
                                'check_out' => $data['check_out'],
                                'guests' => $data['guests'],
                                'total_price' => $total,
                                'minimum_payment_kobo' => AssistedReservationPayments::minimum($total, (int) (new \DateTimeImmutable($data['check_in']))->diff(new \DateTimeImmutable($data['check_out']))->days),
                                'status' => 'pending',
                                'hold_expires_at' => now()->addMinutes(15),
                                'booking_contact' => [
                                    'name' => $data['guest_name'],
                                    'phone' => $data['guest_phone'],
                                    'email' => $data['guest_email'] ?? null,
                                ],
                                'special_requests' => $data['special_requests'] ?? null,
                            ]);
                            \App\Services\ReservationGuestCommunication::sendUpdate($booking, 'held');
                            return $booking;
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
