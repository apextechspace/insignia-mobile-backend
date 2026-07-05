<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceBookingResource\Pages;
use App\Filament\Resources\ServiceBookingResource\RelationManagers;
use App\Models\ServiceBooking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceBookingResource extends Resource
{
    protected static ?string $model = ServiceBooking::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Concierge';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('service_id')
                    ->relationship('service', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('service_variant_id', null)),
                Forms\Components\Select::make('service_variant_id')
                    ->label('Service Variant')
                    ->options(function (Forms\Get $get) {
                        $serviceId = $get('service_id');
                        if (!$serviceId) {
                            return [];
                        }
                        return \App\Models\ServiceVariant::where('service_id', $serviceId)
                            ->pluck('name', 'id');
                    })
                    ->visible(fn (Forms\Get $get) => \App\Models\Service::find($get('service_id'))?->requires_variant ?? false)
                    ->required(fn (Forms\Get $get) => \App\Models\Service::find($get('service_id'))?->requires_variant ?? false)
                    ->reactive()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        if ($state) {
                            $variant = \App\Models\ServiceVariant::find($state);
                            $set('total_price', $variant->price);
                        }
                    }),
                Forms\Components\DatePicker::make('date'),
                Forms\Components\TimePicker::make('time'),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->helperText('Override date/time if needed for a specific timestamp'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'in-progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required()
                    ->default('pending'),
                Forms\Components\TextInput::make('total_price')
                    ->required()
                    ->numeric()
                    ->prefix('₦'),
                Forms\Components\Section::make('Metadata / Dynamic Fields')
                    ->description('Specific details based on the service type')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->keyLabel('Field Name')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('payment_reference'),
                Forms\Components\FileUpload::make('receipt_url')
                    ->label('Payment Proof')
                    ->directory('receipts')
                    ->image(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('variant.name')
                    ->label('Variant')
                    ->placeholder('N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('time')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->money('NGN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'in-progress' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->notes)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_reference')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('receipt_url')
                    ->label('Receipt')
                    ->disk('public')
                    ->circular()
                    ->action(
                        Tables\Actions\Action::make('view_receipt')
                            ->modalHeading('Proof of Payment')
                            ->modalWidth('4xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->infolist([
                                \Filament\Infolists\Components\ImageEntry::make('receipt_url')
                                    ->label('')
                                    ->disk('public')
                                    ->height('600px')
                                    ->width('100%')
                            ])
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'in-progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListServiceBookings::route('/'),
            'create' => Pages\CreateServiceBooking::route('/create'),
            'view' => Pages\ViewServiceBooking::route('/{record}'),
            'edit' => Pages\EditServiceBooking::route('/{record}/edit'),
        ];
    }
}
