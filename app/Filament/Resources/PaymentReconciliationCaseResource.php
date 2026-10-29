<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentReconciliationCaseResource\Pages;
use App\Models\PaymentReconciliationCase;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentReconciliationCaseResource extends Resource
{
    protected static ?string $model = PaymentReconciliationCase::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Payment Exceptions';
    protected static ?string $navigationGroup = 'Finance';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isIplAdmin() ?? false;
    }

    public static function canView($record): bool
    {
        return Auth::user()?->isIplAdmin() ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Payment Reference')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('gateway')->badge(),
                Tables\Columns\TextColumn::make('provider_transaction_id')
                    ->label('Provider Transaction')->copyable(),
                Tables\Columns\TextColumn::make('payable_type')
                    ->label('Transaction Type')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
                Tables\Columns\TextColumn::make('payable_id')->label('Record #'),
                Tables\Columns\TextColumn::make('reason')
                    ->badge()->color('warning'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()->color(fn (string $state) => $state === 'needs_review' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('resolution_notes')->limit(90)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['needs_review' => 'Needs Review', 'resolved' => 'Resolved']),
                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'room_date_conflict' => 'Room dates conflict',
                        'product_out_of_stock' => 'Product stock unavailable after payment',
                        'payable_inactive' => 'Booking or order inactive',
                        'already_paid_other_attempt' => 'Duplicate payment',
                        'payable_amount_changed' => 'Price changed after checkout',
                        'transaction_id_reused' => 'Provider ID reused',
                        'transaction_id_mismatch' => 'Provider ID mismatch',
                    ]),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentReconciliationCases::route('/'),
            'view' => Pages\ViewPaymentReconciliationCase::route('/{record}'),
        ];
    }
}
