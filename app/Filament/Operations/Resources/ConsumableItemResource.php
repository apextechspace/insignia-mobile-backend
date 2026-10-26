<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\ConsumableItemResource\Pages;
use App\Models\ConsumableItem;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

class ConsumableItemResource extends Resource
{
    protected static ?string $model = ConsumableItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Inventory';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Item Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Item Name')
                            ->required()
                            ->placeholder('e.g. Bath Towel, Soap 25g'),
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options([
                                'toiletries'       => '🧴 Toiletries',
                                'linen'            => '🛏 Linen & Bedding',
                                'cleaning_supplies' => '🧹 Cleaning Supplies',
                                'kitchen'          => '🍳 Kitchen',
                                'minibar'          => '🧃 Minibar',
                                'stationery'       => '📝 Stationery',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('unit')
                            ->label('Unit of Measure')
                            ->required()
                            ->placeholder('pieces, bottles, packs, sets'),
                        Forms\Components\TextInput::make('current_stock')
                            ->label('Current Stock')
                            ->numeric()
                            ->default(0)
                            ->disabled(fn(string $operation): bool => $operation === 'edit'),
                        Forms\Components\TextInput::make('minimum_alert_level')
                            ->label('Low Stock Alert Threshold')
                            ->numeric()
                            ->default(5),
                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Unit Cost (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->visible(fn(): bool => Auth::user()?->isIplAdmin()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Item')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\BadgeColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit'),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn(ConsumableItem $record): string =>
                        $record->current_stock <= $record->minimum_alert_level ? 'danger' : 'success'
                    )
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('minimum_alert_level')
                    ->label('Min. Level'),
                Tables\Columns\IconColumn::make('low_stock')
                    ->label('⚠️ Low?')
                    ->getStateUsing(fn(ConsumableItem $record): bool =>
                        $record->current_stock <= $record->minimum_alert_level
                    )
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('NGN')
                    ->visible(fn(): bool => Auth::user()?->isIplAdmin()),
            ])
            ->defaultSort('name')
            ->actions([
                Action::make('issue_to_room')
                    ->label('Issue to Room')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('room_id')
                            ->label('Issue to Room')
                            ->relationship('stockMovements.room', 'name')
                            ->options(fn() => \App\Models\Room::pluck('name', 'id'))
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity to Issue')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (ConsumableItem $record, array $data): void {
                        StockMovement::create([
                            'consumable_item_id' => $record->id,
                            'movement_type'      => 'room_issuance',
                            'quantity'           => -abs($data['quantity']),
                            'room_id'            => $data['room_id'],
                            'handled_by'         => Auth::id(),
                            'notes'              => $data['notes'] ?? null,
                        ]);
                        $record->decrement('current_stock', abs($data['quantity']));
                    }),
                Action::make('restock')
                    ->label('Restock')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn(): bool => !Auth::user()?->isStaff())
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity Received')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (ConsumableItem $record, array $data): void {
                        StockMovement::create([
                            'consumable_item_id' => $record->id,
                            'movement_type'      => 'purchase_restock',
                            'quantity'           => abs($data['quantity']),
                            'handled_by'         => Auth::id(),
                            'notes'              => $data['notes'] ?? null,
                        ]);
                        $record->increment('current_stock', abs($data['quantity']));
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn(): bool => !Auth::user()?->isStaff()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListConsumableItems::route('/'),
            'create' => Pages\CreateConsumableItem::route('/create'),
            'edit'   => Pages\EditConsumableItem::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return !Auth::user()?->isStaff();
    }

    public static function canViewAny(): bool
    {
        return Auth::check();
    }
}
