<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Alignment;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Identity Management';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('role')
                            ->options([
                                User::ROLE_ADMIN => 'System Super Admin',
                                User::ROLE_IPL_ADMIN => 'IPL Property Owner (Master)',
                                User::ROLE_DELIVERY_PARTNER => 'MCL Facility Manager',
                                User::ROLE_GUEST_RELATIONS => 'Front Desk / Guest Relations',
                                User::ROLE_STAFF => 'Housekeeping / Ops Staff',
                                User::ROLE_USER => 'Executive Guest',
                            ])
                            ->required()
                            ->default(User::ROLE_USER),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('avatar_url')
                            ->label('Profile Badge')
                            ->image()
                            ->disk('public')
                            ->directory('avatars')
                            ->avatar()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->getStateUsing(fn ($record) => match(true) {
                        empty($record->avatar_url) => null,
                        str_starts_with($record->avatar_url, 'http') => $record->avatar_url,
                        str_starts_with($record->avatar_url, '/storage/') => url($record->avatar_url),
                        default => url('/storage/' . $record->avatar_url),
                    })
                    ->defaultImageUrl(fn ($record) =>
                        'https://ui-avatars.com/api/?background=4B1D3F&color=fff&name=' . urlencode($record->name ?? 'Guest') . '&size=100'
                    ),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        User::ROLE_ADMIN => 'Super Admin',
                        User::ROLE_IPL_ADMIN => 'IPL Owner',
                        User::ROLE_DELIVERY_PARTNER => 'MCL Manager',
                        User::ROLE_GUEST_RELATIONS => 'Front Desk',
                        User::ROLE_STAFF => 'Housekeeping / Staff',
                        User::ROLE_USER, 'guest' => 'Guest',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        User::ROLE_ADMIN, User::ROLE_IPL_ADMIN => 'danger',
                        User::ROLE_DELIVERY_PARTNER => 'warning',
                        User::ROLE_GUEST_RELATIONS => 'info',
                        User::ROLE_STAFF => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        User::ROLE_ADMIN => 'System Super Admin',
                        User::ROLE_IPL_ADMIN => 'IPL Property Owner',
                        User::ROLE_DELIVERY_PARTNER => 'MCL Facility Manager',
                        User::ROLE_GUEST_RELATIONS => 'Front Desk / Guest Relations',
                        User::ROLE_STAFF => 'Housekeeping / Ops Staff',
                        User::ROLE_USER => 'Executive Guest',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Executive Dossier')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\ImageEntry::make('avatar_url')
                                    ->label('')
                                    ->circular()
                                    ->height(120)
                                    ->state(fn ($record) => match(true) {
                                        empty($record->avatar_url) => 'https://ui-avatars.com/api/?background=4B1D3F&color=fff&name=' . urlencode($record->name ?? 'Guest') . '&size=200',
                                        str_starts_with($record->avatar_url, 'http') => $record->avatar_url,
                                        str_starts_with($record->avatar_url, '/storage/') => url($record->avatar_url),
                                        default => url('/storage/' . $record->avatar_url),
                                    }),
                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('name')
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->weight(FontWeight::Bold),
                                    Infolists\Components\TextEntry::make('email')
                                        ->copyable()
                                        ->icon('heroicon-m-envelope'),
                                    Infolists\Components\TextEntry::make('phone')
                                        ->icon('heroicon-m-phone'),
                                ])->columnSpan(2),
                            ]),
                    ]),

                Infolists\Components\Tabs::make('History')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('bookings_count')
                                            ->label('Total Residencies')
                                            ->state(fn (User $record): int => $record->bookings()->count())
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('service_bookings_count')
                                            ->label('Concierge Requests')
                                            ->state(fn (User $record): int => $record->serviceBookings()->count())
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('orders_count')
                                            ->label('Boutique Purchases')
                                            ->state(fn (User $record): int => $record->orders()->count())
                                            ->weight(FontWeight::Bold),
                                    ]),
                                Infolists\Components\Grid::make(2)
                                    ->schema([
                                        Infolists\Components\IconEntry::make('has_security_pin')
                                            ->label('Security PIN Set')
                                            ->boolean(),
                                        Infolists\Components\TextEntry::make('expo_push_token')
                                            ->label('Mobile Connectivity')
                                            ->state(fn ($record) => $record->expo_push_token ? 'Connected' : 'Not Connected')
                                            ->badge()
                                            ->color(fn ($state) => $state === 'Connected' ? 'success' : 'gray'),
                                    ]),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Active Bookings')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('bookings')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('room.name')
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('check_in')
                                            ->date(),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge(),
                                    ])
                                    ->columns(3),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Concierge Requests')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('serviceBookings')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('service.name')
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('scheduled_at')
                                            ->dateTime(),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge(),
                                    ])
                                    ->columns(3),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Guest Registrations')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('guestRegistrations')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('guest_name')
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('relationship')
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('visit_date')
                                            ->date(),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge(),
                                    ])
                                    ->columns(4),
                            ]),
                        Infolists\Components\Tabs\Tab::make('Maintenance Requests')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('maintenanceRequests')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('title')
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('category')
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('priority')
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge(),
                                    ])
                                    ->columns(4),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
