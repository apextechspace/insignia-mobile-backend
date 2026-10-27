<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\DailyReconciliationResource\Pages;
use App\Models\DailyReconciliation;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DailyReconciliationResource extends Resource
{
    protected static ?string $model = DailyReconciliation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Daily Reconciliation';
    protected static ?string $modelLabel = 'Reconciliation';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shift Details')
                    ->schema([
                        Forms\Components\DatePicker::make('reconciliation_date')
                            ->label('Date')
                            ->default(today())
                            ->required(),
                        Forms\Components\Select::make('shift')
                            ->label('Shift')
                            ->options([
                                'morning' => '🌅 Morning Shift',
                                'evening' => '🌆 Evening Shift',
                                'night'   => '🌙 Night Shift',
                            ])
                            ->default('morning')
                            ->required()
                            ->native(false),
                    ])->columns(2),
                Forms\Components\Section::make('Recorded Figures (System)')
                    ->description('System recorded amounts for this shift period.')
                    ->schema([
                        Forms\Components\TextInput::make('recorded_accommodation')
                            ->label('Accommodation Revenue (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0),
                        Forms\Components\TextInput::make('recorded_ancillary_cash')
                            ->label('Ancillary Non-App Collections (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0),
                    ])->columns(2),
                Forms\Components\Section::make('Verified Digital Collections (Cashless Property)')
                    ->description('Verify POS terminal settlement receipts and confirmed bank transfer credits.')
                    ->schema([
                        Forms\Components\TextInput::make('actual_pos_total')
                            ->label('POS Terminal Total (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) =>
                                $set('discrepancy', (float)$get('recorded_accommodation') + (float)$get('recorded_ancillary_cash') - (float)$get('actual_pos_total') - (float)$get('actual_transfer_total') - (float)$get('actual_cash_counted'))
                            ),
                        Forms\Components\TextInput::make('actual_transfer_total')
                            ->label('Bank Transfer Total (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) =>
                                $set('discrepancy', (float)$get('recorded_accommodation') + (float)$get('recorded_ancillary_cash') - (float)$get('actual_pos_total') - (float)$get('actual_transfer_total') - (float)$get('actual_cash_counted'))
                            ),
                        Forms\Components\TextInput::make('actual_cash_counted')
                            ->label('Physical Cash (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->helperText('Cashless Policy: Property operates on zero-cash policy (Default ₦0)')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) =>
                                $set('discrepancy', (float)$get('recorded_accommodation') + (float)$get('recorded_ancillary_cash') - (float)$get('actual_pos_total') - (float)$get('actual_transfer_total') - (float)$get('actual_cash_counted'))
                            ),
                        Forms\Components\TextInput::make('discrepancy')
                            ->label('Discrepancy (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->default(0)
                            ->helperText('Positive = shortfall, Negative = surplus'),
                    ])->columns(2),
                Forms\Components\Section::make('Supporting Documents & Notes')
                    ->schema([
                        Forms\Components\FileUpload::make('pos_settlement_receipt')
                            ->label('POS Settlement Receipt')
                            ->image()
                            ->directory('reconciliation-receipts'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Shift Notes / Comments')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $user = Auth::user();
                if (!$user?->isIplAdmin()) {
                    $query->where('submitted_by', $user->id);
                }
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('reconciliation_date')
                    ->label('Date')
                    ->date('D, d M Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('shift')
                    ->label('Shift')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'morning' => '🌅 Morning', 'evening' => '🌆 Evening', 'night' => '🌙 Night',
                        default   => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('recorded_accommodation')
                    ->label('Accommodation (₦)')
                    ->money('NGN'),
                Tables\Columns\TextColumn::make('actual_pos_total')
                    ->label('POS Total (₦)')
                    ->money('NGN'),
                Tables\Columns\TextColumn::make('actual_transfer_total')
                    ->label('Transfer Total (₦)')
                    ->money('NGN'),
                Tables\Columns\TextColumn::make('discrepancy')
                    ->label('Discrepancy (₦)')
                    ->money('NGN')
                    ->color(fn(DailyReconciliation $record): string =>
                        abs((float) $record->discrepancy) < 0.01 ? 'success' : 'danger'
                    )
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'submitted',
                        'warning' => 'verified_by_mcl',
                        'success' => 'approved_by_ipl',
                    ]),
                Tables\Columns\TextColumn::make('submittedBy.name')
                    ->label('Submitted By')
                    ->visible(fn(): bool => Auth::user()?->isIplAdmin()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since(),
            ])
            ->defaultSort('reconciliation_date', 'desc')
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn(DailyReconciliation $record): bool =>
                        Auth::user()?->isIplAdmin() && $record->status !== 'approved_by_ipl'
                    )
                    ->requiresConfirmation()
                    ->action(function (DailyReconciliation $record): void {
                        $record->update([
                            'status'      => 'approved_by_ipl',
                            'verified_by' => Auth::id(),
                            'verified_at' => now(),
                        ]);
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn(DailyReconciliation $record): bool =>
                        $record->status === 'submitted' && $record->submitted_by === Auth::id()
                    ),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDailyReconciliations::route('/'),
            'create' => Pages\CreateDailyReconciliation::route('/create'),
            'edit'   => Pages\EditDailyReconciliation::route('/{record}/edit'),
            'view'   => Pages\ViewDailyReconciliation::route('/{record}'),
        ];
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && $record->status === 'submitted'
            && (int) $record->submitted_by === (int) Auth::id();
    }

    public static function canDelete($record): bool { return false; }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS,
        ]);
    }
}
