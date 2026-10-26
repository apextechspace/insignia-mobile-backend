<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FoodOrderResource\Pages;
use App\Models\FoodOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FoodOrderResource extends Resource
{
    protected static ?string $model = FoodOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Dining Management';
    protected static ?string $navigationLabel = 'Food Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'preparing' => 'Preparing',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'room_service' => 'Room Service',
                        'pickup' => 'Pickup',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('payment_reference'),
                Forms\Components\FileUpload::make('receipt_url')
                    ->label('Payment Proof')
                    ->directory('receipts')
                    ->image(),
                Forms\Components\KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('total_amount')->money('NGN')->sortable(),
                Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'preparing' => 'Preparing',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'flutterwave' => '⚡ Flutterwave',
                        'opay' => 'OPay (Historical)',
                        'bank_transfer' => '🏦 Bank Transfer',
                        default => 'Not specified',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'flutterwave' => 'success',
                        'opay' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('payment_reference')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\ImageColumn::make('receipt_url')
                    ->label('Receipt')
                    ->disk('public')
                    ->circular(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'preparing' => 'Preparing',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Can add FoodOrderItems relation manager here later if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFoodOrders::route('/'),
            'edit' => Pages\EditFoodOrder::route('/{record}/edit'),
        ];
    }
}
