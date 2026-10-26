<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\AccommodationLedgerResource\Pages;
use App\Models\AccommodationLedger;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

class AccommodationLedgerResource extends Resource
{
    protected static ?string $model = AccommodationLedger::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Accommodation Ledger';
    protected static ?string $modelLabel = 'Ledger Entry';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ledger Entry')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference')
                            ->disabled()
                            ->placeholder('Auto-generated')
                            ->dehydrated(false),
                        Forms\Components\Select::make('booking_id')
                            ->label('Booking')
                            ->relationship('booking', 'id')
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('room_id')
                            ->label('Room')
                            ->relationship('room', 'name')
                            ->native(false),
                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options([
                                'revenue'    => 'Revenue',
                                'refund'     => 'Refund',
                                'adjustment' => 'Adjustment',
                            ])
                            ->native(false),
                        Forms\Components\TextInput::make('gross_amount')
                            ->label('Gross Amount (₦)')
                            ->numeric()->prefix('₦'),
                        Forms\Components\TextInput::make('net_amount')
                            ->label('Net Amount (₦)')
                            ->numeric()->prefix('₦'),
                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'flutterwave'   => 'Flutterwave',
                        'opay'          => 'OPay (Historical)',
                                'bank_transfer' => 'Bank Transfer',
                                'pos'           => 'POS Terminal',
                            ])
                            ->default('bank_transfer')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('channel')
                            ->label('Channel')
                            ->options([
                                'app'     => 'Mobile App',
                                'walk_in' => 'Walk-in',
                                'direct'  => 'Direct Booking',
                            ])
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending'  => 'Pending',
                                'confirmed' => 'Confirmed',
                                'disputed' => 'Disputed',
                                'refunded' => 'Refunded',
                            ])
                            ->native(false),
                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Transaction Date'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Room')
                    ->badge(),
                Tables\Columns\TextColumn::make('booking.id')
                    ->label('Booking #')
                    ->prefix('#'),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'success' => 'revenue',
                        'danger'  => 'refund',
                        'warning' => 'adjustment',
                    ]),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Gross (₦)')
                    ->money('NGN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Net (₦)')
                    ->money('NGN'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => strtoupper(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger'  => 'disputed',
                        'gray'    => 'refunded',
                    ]),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                SelectFilter::make('type')->options(['revenue' => 'Revenue', 'refund' => 'Refund', 'adjustment' => 'Adjustment']),
                SelectFilter::make('status')->options(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'disputed' => 'Disputed']),
                SelectFilter::make('channel')->options(['app' => 'Mobile App', 'walk_in' => 'Walk-in', 'direct' => 'Direct']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn(): bool => Auth::user()?->isIplAdmin()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAccommodationLedgers::route('/'),
            'create' => Pages\CreateAccommodationLedger::route('/create'),
            'edit'   => Pages\EditAccommodationLedger::route('/{record}/edit'),
        ];
    }

    /**
     * IPL sees all. MCL (delivery_partner) sees read-only.
     * Staff and guest_relations are blocked.
     */
    public static function canViewAny(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN, User::ROLE_DELIVERY_PARTNER,
        ]);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isIplAdmin();
    }
}
