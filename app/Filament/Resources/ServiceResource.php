<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Resources\ServiceResource\RelationManagers;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Concierge';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('category')
                    ->options([
                        'Interiors' => 'Interiors',
                        'Dining' => 'Dining',
                        'Wellness' => 'Wellness',
                        'Concierge' => 'Concierge',
                        'Transport' => 'Transport',
                        'Experiences' => 'Experiences',
                        'Workspace' => 'Workspace',
                    ])
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'booking' => 'Booking (Variants, Date, Time)',
                        'reservation' => 'Reservation (Date Range, Guests)',
                        'request' => 'Request (Custom Details)',
                    ])
                    ->required()
                    ->default('booking'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('₦')
                    ->helperText('Base price if no variants are required'),
                Forms\Components\FileUpload::make('image')
                    ->image()
                    ->directory('services'),
                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->default(true),
                Forms\Components\Toggle::make('requires_variant')
                    ->label('Requires Variant Selection')
                    ->reactive()
                    ->default(false),

                Forms\Components\Section::make('Service Variants')
                    ->description('Manage durations or types of this service')
                    ->hidden(fn (Forms\Get $get) => !$get('requires_variant'))
                    ->schema([
                        Forms\Components\Repeater::make('variants')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->placeholder('e.g., 1 Hour or 30 Minutes'),
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->required()
                                    ->prefix('₦'),
                                Forms\Components\TextInput::make('duration_minutes')
                                    ->numeric()
                                    ->label('Duration (mins)'),
                            ])
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->circular()
                    ->disk('public'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'booking' => 'success',
                        'reservation' => 'info',
                        'request' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->money('NGN')
                    ->sortable(),
                Tables\Columns\IconColumn::make('requires_variant')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
