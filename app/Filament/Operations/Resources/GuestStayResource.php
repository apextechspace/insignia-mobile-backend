<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\GuestStayResource\Pages;
use App\Models\Booking;
use App\Models\User;
use App\Services\StayOperationsService;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
    public static function canView($record): bool { return self::canViewAny(); }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['user', 'room', 'checkedInBy', 'checkedOutBy']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function stayStage(Booking $record): string
    {
        if ($record->checked_out_at) return 'Checked out';
        if ($record->checked_in_at) return 'In house';
        if (!$record->paid_at && $record->status === 'cancelled') return 'Cancelled';
        if (!$record->paid_at && $record->hold_expires_at?->lte(now())) return 'Checkout expired';
        if (!$record->paid_at) return 'Awaiting payment';
        if (in_array($record->status, ['confirmed', 'paid'], true)) return 'Awaiting arrival';
        return ucfirst($record->status);
    }

    public static function canCheckIn(Booking $record): bool
    {
        return self::canViewAny() && $record->paid_at
            && !$record->checked_in_at && !$record->checked_out_at
            && in_array($record->status, ['confirmed', 'paid'], true);
    }

    public static function canCheckOut(Booking $record): bool
    {
        return self::canViewAny() && $record->checked_in_at && !$record->checked_out_at
            && in_array($record->status, ['confirmed', 'paid'], true);
    }

    public static function registrationForm(): array
    {
        return [
            Components\Placeholder::make('arrival_review')->label('Before completing check-in')
                ->content('Verify the guest identity and review payment, stay dates and room readiness. Hand over keys after check-in succeeds.'),
            Components\TextInput::make('full_name')->label('Arriving guest full name')->required()->maxLength(255),
            Components\TextInput::make('phone')->label('Guest phone')->tel()->required()->maxLength(40),
            Components\TextInput::make('adults')->numeric()->integer()->minValue(1)->maxValue(20)->required(),
            Components\TextInput::make('children')->numeric()->integer()->minValue(0)->maxValue(20)->required(),
            Components\Textarea::make('notes')->label('Arrival notes')->maxLength(2000)
                ->helperText('Optional requests or handover notes for this stay.'),
            Components\Checkbox::make('identity_verified')->label('I have checked the arriving guest’s identity')
                ->accepted()->required(),
        ];
    }

    public static function registrationDefaults(Booking $record): array
    {
        return [
            'full_name' => $record->booking_contact['name'] ?? $record->user?->name,
            'phone' => $record->booking_contact['phone'] ?? $record->user?->phone,
            'adults' => $record->guests ?: 1, 'children' => 0, 'identity_verified' => false,
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Reservation overview')->schema([
                TextEntry::make('id')->label('Booking #'),
                TextEntry::make('stay_stage')->label('Guest stay')->state(fn (Booking $record) => self::stayStage($record))->badge(),
                TextEntry::make('room.name')->label('Suite'),
                TextEntry::make('room.status')->label('Current room readiness')->badge(),
                TextEntry::make('check_in')->label('Scheduled arrival')->date(),
                TextEntry::make('check_out')->label('Scheduled departure')->date(),
                TextEntry::make('arrival_guidance')->label('Next step')->state(fn (Booking $record) => match (true) {
                    (bool) $record->checked_out_at => 'Stay completed. Housekeeping must finish and a supervisor must verify the room before it is released.',
                    (bool) $record->checked_in_at => 'Guest is in house. On departure, collect keys and complete checkout to start housekeeping.',
                    !$record->paid_at => 'Await verified payment before checking in this guest.',
                    !in_array($record->status, ['confirmed', 'paid'], true) => 'This reservation is not active for check-in. Review its status with finance or operations.',
                    today()->toDateString() < $record->check_in => 'Arrival is scheduled for a future date. Check-in opens on the arrival date.',
                    today()->toDateString() >= $record->check_out => 'The scheduled stay has ended. Review the reservation before processing an arrival.',
                    !in_array($record->room?->status, ['available', 'reserved'], true) => 'The suite is not ready. Resolve room readiness before check-in.',
                    default => 'Review the guest details, complete registration and verify identity. Hand over keys after check-in succeeds.',
                })->columnSpanFull(),
            ])->columns(3),
            Section::make('Booking contact')->schema([
                TextEntry::make('user.name')->label('Booked by')->placeholder('Not provided'),
                TextEntry::make('user.email')->label('Email')->placeholder('Not provided'),
                TextEntry::make('user.phone')->label('Phone')->placeholder('Not provided'),
                TextEntry::make('booking_contact.name')->label('Lead guest name')->placeholder('Not provided'),
                TextEntry::make('booking_contact.phone')->label('Lead guest phone')->placeholder('Not provided'),
                TextEntry::make('guests')->label('Guests'),
                TextEntry::make('special_requests')->label('Booking requests')->placeholder('None')->columnSpanFull(),
            ])->columns(3),
            Section::make('Payment verification')->schema([
                TextEntry::make('total_price')->label('Reservation total')->money('NGN'),
                TextEntry::make('payment_verification')->label('Payment')->state(fn (Booking $record) => $record->paid_at ? 'Verified paid' : 'Not verified')->badge(),
                TextEntry::make('payment_method')->label('Method')->placeholder('Not provided'),
                TextEntry::make('paid_at')->label('Verified at')->dateTime()->placeholder('Awaiting payment'),
                TextEntry::make('payment_reference')->label('Booking reference')->placeholder('Not provided')->copyable(),
                TextEntry::make('flutterwave_reference')->label('Gateway reference')->placeholder('Not applicable')->copyable(),
            ])->columns(3),
            Section::make('Guest registration')->schema([
                TextEntry::make('guest_registration.full_name')->label('Arriving guest')->placeholder('Complete at check-in'),
                TextEntry::make('guest_registration.phone')->label('Guest phone')->placeholder('Complete at check-in'),
                TextEntry::make('guest_registration.adults')->label('Adults')->placeholder('Not recorded'),
                TextEntry::make('guest_registration.children')->label('Children')->placeholder('Not recorded'),
                IconEntry::make('guest_registration.identity_verified')->label('Identity checked')->boolean(),
                TextEntry::make('guest_registration.notes')->label('Arrival notes')->placeholder('No notes')->columnSpanFull(),
            ])->columns(3),
            Section::make('Arrival and departure history')->schema([
                TextEntry::make('checked_in_at')->label('Actual check-in')->dateTime()->placeholder('Not checked in'),
                TextEntry::make('checkedInBy.name')->label('Checked in by')->placeholder('Not recorded'),
                TextEntry::make('checked_out_at')->label('Actual checkout')->dateTime()->placeholder('Not checked out'),
                TextEntry::make('checkedOutBy.name')->label('Checked out by')->placeholder('Not recorded'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Booking $record) => self::getUrl('view', ['record' => $record]))
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
                Tables\Columns\TextColumn::make('stay_stage')->label('Guest stay')
                    ->state(fn (Booking $record) => self::stayStage($record))->badge(),
            ])
            ->filters([
                Tables\Filters\Filter::make('arrivals_today')->label('Arrivals today')
                    ->query(fn ($query) => $query->whereDate('check_in', today())->whereNull('checked_in_at')->whereNull('checked_out_at')),
                Tables\Filters\Filter::make('in_house')->label('In house')
                    ->query(fn ($query) => $query->whereNotNull('checked_in_at')->whereNull('checked_out_at')),
                Tables\Filters\Filter::make('departures_due')->label('Departures due')
                    ->query(fn ($query) => $query->whereNotNull('checked_in_at')->whereNull('checked_out_at')->whereDate('check_out', '<=', today())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Guest details'),
                Tables\Actions\Action::make('check_in')
                    ->label('Check In')->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')->modalHeading('Register guest and check in')
                    ->modalSubmitActionLabel('Complete check-in')
                    ->form(self::registrationForm())
                    ->fillForm(fn (Booking $record) => self::registrationDefaults($record))
                    ->visible(fn (Booking $record) => self::canCheckIn($record))
                    ->action(fn (Booking $record, array $data) => self::processStayAction($record, 'checkIn', $data)),
                Tables\Actions\Action::make('check_out')
                    ->label('Check Out')->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('warning')->requiresConfirmation()
                    ->modalDescription('Collect keys and confirm the guest has departed. This completes the stay and creates the housekeeping checklist.')
                    ->visible(fn (Booking $record) => self::canCheckOut($record))
                    ->action(fn (Booking $record) => self::processStayAction($record, 'checkOut')),
            ]);
    }

    public static function processStayAction(Booking $record, string $method, array $data = []): void
    {
        abort_unless(in_array($method, ['checkIn', 'checkOut'], true) && self::canViewAny(), 403);
        $checkingIn = $method === 'checkIn';

        try {
            if ($checkingIn) {
                app(StayOperationsService::class)->checkIn($record, Auth::user(), $data);
            } else {
                app(StayOperationsService::class)->checkOut($record, Auth::user());
            }
        } catch (ValidationException $exception) {
            Notification::make()
                ->title($checkingIn ? 'Check-in blocked' : 'Check-out blocked')
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->danger()
                ->persistent()
                ->send();

            throw new Halt();
        }

        Notification::make()
            ->title($checkingIn ? 'Guest checked in' : 'Guest checked out')
            ->body($checkingIn ? 'Registration saved and suite marked occupied. You can now hand over the keys.' : 'Stay completed. The suite is marked for cleaning and a housekeeping checklist has been created.')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGuestStays::route('/'),
            'view' => Pages\ViewGuestStay::route('/{record}'),
        ];
    }
}
