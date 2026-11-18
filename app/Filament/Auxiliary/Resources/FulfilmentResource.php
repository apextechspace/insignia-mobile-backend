<?php

namespace App\Filament\Auxiliary\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class FulfilmentResource extends Resource
{
    protected static ?string $navigationGroup = 'Fulfilment';

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, [
            \App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_IPL_ADMIN, \App\Models\User::ROLE_AUXILIARY,
        ], true);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    abstract public static function transitions(): array;

    public static function table(Table $table): Table
    {
        $dining = static::getModel() === \App\Models\FoodOrder::class;
        $shop = static::getModel() === \App\Models\Order::class;
        $actions = [];
        foreach (static::transitions() as $from => $to) {
            $actions[] = Tables\Actions\Action::make('progress_'.str_replace('-', '_', $from))
                ->label(ucfirst(str_replace('-', ' ', $to)))
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn (Model $record) => filled($record->paid_at) && $record->status === $from)
                ->requiresConfirmation()
                ->action(function (Model $record) use ($from, $to): void {
                    abort_unless(static::canViewAny(), 403);
                    DB::transaction(function () use ($record, $from, $to): void {
                        $current = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                        abort_unless($current->paid_at && $current->status === $from, 409);
                        $current->update(['status' => $to]);
                        DB::table('auxiliary_fulfilment_events')->insert([
                            'order_type' => $current->getMorphClass(),
                            'order_id' => $current->id,
                            'handled_by' => auth()->id(),
                            'previous_status' => $from,
                            'status' => $to,
                            'created_at' => now(),
                        ]);
                    });
                });
        }
        array_unshift($actions, Tables\Actions\Action::make('details')->label('Order Details')
            ->icon('heroicon-o-document-text')->modalHeading('Fulfilment details')
            ->modalSubmitAction(false)->modalCancelActionLabel('Close')
            ->modalContent(fn (Model $record) => view('filament.auxiliary.order-details', ['record' => $record])));

        return $table->modifyQueryUsing(fn ($query) => $query->whereNotNull('paid_at'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Reference')->prefix('#')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Guest')->searchable(),
                Tables\Columns\TextColumn::make($dining ? 'type' : ($shop ? 'product.name' : 'service.name'))
                    ->label($dining ? 'Delivery' : ($shop ? 'Product' : 'Service'))->searchable(),
                Tables\Columns\TextColumn::make($shop ? 'delivery_location' : ($dining ? 'metadata.room_number' : 'scheduled_at'))
                    ->label($shop || $dining ? 'Delivery Location' : 'Scheduled')
                    ->placeholder('See order details'),
                Tables\Columns\TextColumn::make($dining ? 'total_amount' : 'total_price')->label('Order Total')->money('NGN'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('paid_at')->label('Payment verified')->dateTime()->timezone(config('property.timezone')),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->options(
                collect(static::transitions())->keys()->merge(array_values(static::transitions()))->unique()
                    ->mapWithKeys(fn ($status) => [$status => ucfirst(str_replace('-', ' ', $status))])->all()
            )])
            ->actions($actions)->defaultSort('id', 'desc')->bulkActions([]);
    }
}
