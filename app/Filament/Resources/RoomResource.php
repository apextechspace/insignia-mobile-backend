<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Filament\Resources\RoomResource\RelationManagers;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Hospitality';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'Standard Room' => 'Standard Room',
                        'Executive Room' => 'Executive Room',
                        'Suite' => 'Suite',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('tag')
                    ->label('Tag Line (e.g. "Most Popular", "Best Value")')
                    ->maxLength(100),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('price_per_night')
                    ->required()
                    ->numeric()
                    ->prefix('₦'),
                Forms\Components\CheckboxList::make('amenities')
                    ->options([
                        'wifi' => 'WiFi',
                        'tv' => 'Smart TV',
                        'pool' => 'Private Pool',
                        'person' => 'Butler Service',
                        'wine' => 'Mini-bar / Wine',
                        'sparkles' => 'Spa Access',
                        'restaurant' => 'Breakfast Included',
                        'fitness' => 'Gym / Fitness',
                        'snow' => 'Air Conditioning',
                        'car' => 'Free Parking',
                        'speedometer' => 'High-speed Internet',
                    ])
                    ->columns(3),
                Forms\Components\FileUpload::make('images')
                    ->multiple()
                    ->directory('rooms')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('video_url')
                    ->label('Room Video (Optional)')
                    ->directory('rooms/videos')
                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                    ->maxSize(51200)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_available')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images')
                    ->label('Photo')
                    ->disk('public')
                    ->stacked()
                    ->limit(1),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price_per_night')
                    ->money('NGN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amenities')
                    ->badge()
                    ->color('info')
                    ->separator(','),
                Tables\Columns\IconColumn::make('is_available')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'Standard Room' => 'Standard Room',
                        'Executive Room' => 'Executive Room',
                        'Suite' => 'Suite',
                    ]),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Availability'),
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
