<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\RoomStatusResource\Pages;
use App\Models\Room;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
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
                    ->helperText('Occupied is managed by Check In/Check Out. Do not mark a checked-in suite as ready.')
                    ->required()
                    ->native(false),
                Forms\Components\Textarea::make('housekeeping_notes')
                            ->label('Housekeeping Notes')
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
                        'danger'  => 'occupied',
                        'warning' => 'cleaning',
                        'gray'    => 'maintenance',
                        'info'    => 'reserved',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'available'   => '✅ Ready',
                        'occupied'    => '🔴 Occupied',
                        'cleaning'    => '🧹 Cleaning',
                        'maintenance' => '🔧 Maintenance',
                        'reserved'    => '📋 Reserved',
                        default       => ucfirst($state),
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
            ])
            ->defaultSort('name')
            ->actions([
                Action::make('mark_ready')
                    ->label('Mark Ready')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Room $record): bool => in_array($record->status, ['cleaning', 'maintenance']))
                    ->requiresConfirmation()
                    ->modalHeading('Mark Room as Ready?')
                    ->modalDescription(fn(Room $record): string => "Confirm that {$record->name} has been cleaned and is ready for the next guest.")
                    ->action(function (Room $record): void {
                        $record->update(['status' => 'available']);
                    }),
                Action::make('mark_cleaning')
                    ->label('Start Cleaning')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn(Room $record): bool => in_array($record->status, ['available', 'occupied']))
                    ->action(function (Room $record): void {
                        $record->update(['status' => 'cleaning']);
                    }),
                Action::make('mark_maintenance')
                    ->label('Flag Maintenance')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('gray')
                    ->visible(fn(Room $record): bool => $record->status !== 'maintenance')
                    ->requiresConfirmation()
                    ->action(function (Room $record): void {
                        $record->update(['status' => 'maintenance']);
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn(): bool => Auth::user()?->isIplAdmin() || Auth::user()?->isDeliveryPartner()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomStatuses::route('/'),
            'edit'  => Pages\EditRoomStatus::route('/{record}/edit'),
        ];
    }

    /**
     * All roles in the operations panel can see rooms.
     */
    public static function canViewAny(): bool
    {
        return Auth::check();
    }
}
