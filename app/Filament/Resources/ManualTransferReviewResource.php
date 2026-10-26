<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManualTransferReviewResource\Pages;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManualTransferReviewResource extends Resource
{
    protected static ?string $model = Booking::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationLabel = 'Verify Bank Transfers';
    protected static ?string $navigationGroup = 'Finance';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isIplAdmin() ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'pending')
            ->whereNull('paid_at')
            ->whereNotNull('receipt_url')
            ->where(function (Builder $query) {
                $query->whereNull('payment_method')->orWhere('payment_method', 'bank_transfer');
            });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('Booking #'),
            Tables\Columns\TextColumn::make('user.name')->label('Guest'),
            Tables\Columns\TextColumn::make('room.name')->label('Suite'),
            Tables\Columns\TextColumn::make('total_price')->money('NGN'),
            Tables\Columns\TextColumn::make('check_in')->date(),
            Tables\Columns\TextColumn::make('check_out')->date(),
            Tables\Columns\TextColumn::make('payment_reference')->copyable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime(),
        ])->actions([
            Tables\Actions\Action::make('approve')
                ->label('Verify bank credit')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('bank_transaction_reference')
                        ->required()->maxLength(255),
                    Forms\Components\TextInput::make('verified_amount')
                        ->numeric()->required()->minValue(1),
                    Forms\Components\Textarea::make('notes')
                        ->label('Bank statement verification notes')->required(),
                ])
                ->requiresConfirmation()
                ->action(function (Booking $record, array $data): void {
                    app(\App\Services\ManualBookingPaymentReview::class)->approve(
                        $record,
                        auth()->user(),
                        $data['bank_transaction_reference'],
                        (string) $data['verified_amount'],
                        $data['notes']
                    );
                }),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManualTransferReviews::route('/'),
        ];
    }
}
