<?php

namespace App\Filament\Auxiliary\Resources;

use Filament\Tables;
use Filament\Tables\Table;

class MenuItemResource extends FulfilmentResource
{
    protected static ?string $model = \App\Models\MenuItem::class;

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Menu';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function transitions(): array
    {
        return [];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('category.name')->label('Category'),
            Tables\Columns\TextColumn::make('price')->money('NGN'),
            Tables\Columns\IconColumn::make('is_available')->boolean(),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => MenuItemResource\Pages\ListMenuItems::route('/')];
    }
}
