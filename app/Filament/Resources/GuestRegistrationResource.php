<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuestRegistrationResource\Pages;
use App\Models\GuestRegistration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GuestRegistrationResource extends Resource
{
    protected static ?string $model = GuestRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Hospitality';

    protected static ?string $navigationLabel = 'Gate Registrations';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->label('Resident')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('guest_name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('guest_phone')
                ->tel()
                ->maxLength(20),
            Forms\Components\Select::make('relationship')
                ->options([
                    'Family'   => 'Family',
                    'Friend'   => 'Friend',
                    'Client'   => 'Client / Business',
                    'Delivery' => 'Delivery',
                    'Other'    => 'Other',
                ]),
            Forms\Components\DatePicker::make('visit_date'),
            Forms\Components\TimePicker::make('expected_arrival'),
            Forms\Components\TextInput::make('purpose')->maxLength(255),
            Forms\Components\Select::make('status')
                ->options([
                    'pending'   => 'Pending',
                    'approved'  => 'Approved',
                    'denied'    => 'Denied',
                    'completed' => 'Completed',
                ])
                ->default('pending')
                ->required(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Resident')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guest_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guest_phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('relationship')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('visit_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expected_arrival'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'approved'  => 'success',
                        'denied'    => 'danger',
                        'completed' => 'gray',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'approved'  => 'Approved',
                        'denied'    => 'Denied',
                        'completed' => 'Completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGuestRegistrations::route('/'),
            'create' => Pages\CreateGuestRegistration::route('/create'),
            'edit'   => Pages\EditGuestRegistration::route('/{record}/edit'),
        ];
    }
}
