<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\ConsumableItemResource\Pages;
use App\Models\ConsumableItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
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
                                'toiletries' => '🧴 Toiletries',
                                'linen' => '🛏 Linen & Bedding',
                                'cleaning_supplies' => '🧹 Cleaning Supplies',
                                'kitchen' => '🍳 Kitchen',
                                'minibar' => '🧃 Minibar',
                                'stationery' => '📝 Stationery',
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
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),
                        Forms\Components\TextInput::make('minimum_alert_level')
                            ->label('Low Stock Alert Threshold')
                            ->numeric()
                            ->default(5),
                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Unit Cost (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->visible(fn (): bool => Auth::user()?->isIplAdmin()),
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
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit'),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn (ConsumableItem $record): string => $record->current_stock <= $record->minimum_alert_level ? 'danger' : 'success'
                    )
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('minimum_alert_level')
                    ->label('Min. Level'),
                Tables\Columns\IconColumn::make('low_stock')
                    ->label('⚠️ Low?')
                    ->getStateUsing(fn (ConsumableItem $record): bool => $record->current_stock <= $record->minimum_alert_level
                    )
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('NGN')
                    ->visible(fn (): bool => Auth::user()?->isIplAdmin()),
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
                            ->options(fn () => \App\Models\Room::pluck('name', 'id'))
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity to Issue')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (ConsumableItem $record, array $data, $livewire): void {
                        self::withStockValidation(fn () => app(\App\Services\ConsumableStockService::class)->issueToRoom($record, Auth::user(), $data), $livewire);
                    }),
                Action::make('restock')
                    ->label('Restock')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (): bool => ! Auth::user()?->isStaff())
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity Received')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (ConsumableItem $record, array $data, $livewire): void {
                        self::withStockValidation(fn () => app(\App\Services\ConsumableStockService::class)->restock($record, Auth::user(), $data), $livewire);
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => ! Auth::user()?->isStaff()),
            ]);
    }

    private static function withStockValidation(callable $action, $livewire): void
    {
        try {
            $action();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $prefix = $livewire->getMountedTableActionForm()->getStatePath();
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[$prefix.'.'.$field] = $messages;
            }
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsumableItems::route('/'),
            'create' => Pages\CreateConsumableItem::route('/create'),
            'edit' => Pages\EditConsumableItem::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return ! Auth::user()?->isStaff();
    }

    public static function canViewAny(): bool
    {
        return Auth::check();
    }
}
