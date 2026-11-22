<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\RoomStatusResource\Pages;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RoomStatusResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Room Status';

    protected static ?string $modelLabel = 'Room';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Room Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Room Name')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->label('Physical Room Status')
                            ->options([
                                'available' => 'Available / Ready',
                                'cleaning' => 'Cleaning In Progress',
                                'maintenance' => 'Under Maintenance',
                                'reserved' => 'Reserved',
                            ])
                            ->helperText('Occupied is managed by Check In/Check Out. Cleaning rooms need a completed checklist and Room Readiness Review → Verify & Release. If the checklist is missing, use Create Readiness Checklist above.')
                            ->required()
                            ->live()
                            ->native(false),
                        Forms\Components\Textarea::make('housekeeping_notes')
                            ->label(fn (Forms\Get $get): string => $get('status') === 'maintenance' ? 'Maintenance Note' : 'Housekeeping Notes')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'maintenance')
                            ->maxLength(1000)
                            ->rows(3)
                            ->placeholder('Any notes for the next shift...')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Apartment')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size('lg'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'available',
                        'danger' => 'occupied',
                        'warning' => 'cleaning',
                        'gray' => 'maintenance',
                        'info' => 'reserved',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => '✅ Ready',
                        'occupied' => '🔴 Occupied',
                        'cleaning' => '🧹 Cleaning',
                        'maintenance' => '🔧 Maintenance',
                        'reserved' => '📋 Reserved',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('activeBooking.check_in')
                    ->label('Check-in')
                    ->date('D, d M Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('activeBooking.check_out')
                    ->label('Check-out')
                    ->date('D, d M Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('activeBooking.user.name')
                    ->label('Current Guest')
                    ->placeholder('Vacant'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('maintenance_note')
                    ->label('Maintenance Note')
                    ->state(fn (Room $record): ?string => $record->status === 'maintenance' ? $record->housekeeping_notes : null)
                    ->wrap(),
            ])
            ->defaultSort('name')
            ->actions([
                Action::make('mark_ready')
                    ->label('Mark Ready')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Room $record): bool => RoomReadinessResource::canViewAny() && in_array($record->status, ['cleaning', 'maintenance']))
                    ->requiresConfirmation()
                    ->modalHeading('Mark Room as Ready?')
                    ->modalDescription(fn (Room $record): string => "Confirm that {$record->name} has been cleaned and is ready for the next guest.")
                    ->action(function (Room $record): void {
                        try {
                            app(\App\Services\RoomTurnoverService::class)->markReady($record, Auth::user());
                        } catch (\Illuminate\Validation\ValidationException $exception) {
                            \Filament\Notifications\Notification::make()->danger()->title('Room is not ready for release')->body(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
                Action::make('mark_cleaning')
                    ->label('Start Cleaning')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (Room $record): bool => $record->status === 'available')
                    ->action(function (Room $record): void {
                        $record->update(['status' => 'cleaning']);
                    }),
                Action::make('mark_maintenance')
                    ->label('Flag Maintenance')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('gray')
                    ->visible(fn (Room $record): bool => $record->status !== 'maintenance')
                    ->form([
                        Forms\Components\Textarea::make('housekeeping_notes')
                            ->label('Maintenance Note')
                            ->helperText('Describe what needs repair. Leave out guest names and contact details.')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Room $record, array $data): void {
                        $record->update([
                            'status' => 'maintenance',
                            'housekeeping_notes' => $data['housekeeping_notes'],
                        ]);
                    }),
                Action::make('share_maintenance')
                    ->label('Share on WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn (Room $record): bool => $record->status === 'maintenance' && filled($record->housekeeping_notes))
                    ->url(fn (Room $record): string => 'https://wa.me/?text='.rawurlencode(
                        "Insignia Court maintenance\nRoom: {$record->name}\nIssue: {$record->housekeeping_notes}"
                    ), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => Auth::user()?->isIplAdmin() || Auth::user()?->isDeliveryPartner()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomStatuses::route('/'),
            'edit' => Pages\EditRoomStatus::route('/{record}/edit'),
        ];
    }

    /**
     * All roles in the operations panel can see rooms.
     */
    public static function canEdit($record): bool
    {
        return Auth::user()?->isIplAdmin() || Auth::user()?->isDeliveryPartner();
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user && ! $user->isStaff();
    }
}
