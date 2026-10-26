<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\Room;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Expenses';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Expense Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Description / Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. PHCN bill – October 2026'),
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options([
                                'utilities_phcn'      => 'Utilities – PHCN / Electricity',
                                'generator_fuel'      => 'Generator Fuel',
                                'maintenance_repairs' => 'Maintenance & Repairs',
                                'consumables_restock' => 'Consumables Restock',
                                'security'            => 'Security',
                                'petty_cash'          => 'Petty Cash',
                                'staff_welfare'       => 'Staff Welfare',
                                'other'               => 'Other',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->required(),
                        Forms\Components\Select::make('payment_method')
                            ->label('Disbursement Method')
                            ->options([
                                'bank_transfer' => 'Bank Transfer (Electronic)',
                                'debit_card'    => 'Company Debit Card / POS',
                                'petty_cash'    => 'Digital Operating Float',
                            ])
                            ->default('bank_transfer')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('room_id')
                            ->label('Related Room (optional)')
                            ->relationship('room', 'name')
                            ->searchable()
                            ->placeholder('Applies to whole property')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('expense_date')
                            ->label('Date of Expense')
                            ->default(now())
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Receipt & Notes')
                    ->schema([
                        Forms\Components\FileUpload::make('receipt_photo')
                            ->label('Receipt / Invoice Photo')
                            ->image()
                            ->imageEditor()
                            ->directory('expense-receipts')
                            ->helperText('Upload a photo of the receipt or invoice'),
                        Forms\Components\Textarea::make('description')
                            ->label('Additional Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Approval Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending_approval' => 'Pending Approval',
                                'approved'         => 'Approved',
                                'rejected'         => 'Rejected',
                            ])
                            ->disabled(fn(): bool => !Auth::user()?->isIplAdmin())
                            ->native(false),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason (IPL only)')
                            ->rows(2)
                            ->visible(fn(): bool => Auth::user()?->isIplAdmin())
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(): bool => Auth::user()?->isIplAdmin()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $user = Auth::user();
                // IPL sees all expenses; non-IPL sees only their own
                if (!$user?->isIplAdmin()) {
                    $query->where('logged_by', $user->id);
                }
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Description')
                    ->searchable()
                    ->limit(40)
                    ->weight('medium'),
                Tables\Columns\BadgeColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'utilities_phcn'      => '💡 PHCN/Utilities',
                        'generator_fuel'      => '⛽ Generator Fuel',
                        'maintenance_repairs' => '🔧 Maintenance',
                        'consumables_restock' => '📦 Consumables',
                        'security'            => '🔒 Security',
                        'petty_cash'          => '💵 Petty Cash',
                        'staff_welfare'       => '👷 Staff Welfare',
                        default               => ucwords(str_replace('_', ' ', $state)),
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('NGN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Room')
                    ->placeholder('Property-wide'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending_approval',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending_approval' => '⏳ Pending',
                        'approved'         => '✅ Approved',
                        'rejected'         => '❌ Rejected',
                        default            => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('loggedBy.name')
                    ->label('Logged By')
                    ->visible(fn(): bool => Auth::user()?->isIplAdmin()),
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('expense_date', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'utilities_phcn'      => 'PHCN/Utilities',
                        'generator_fuel'      => 'Generator Fuel',
                        'maintenance_repairs' => 'Maintenance',
                        'consumables_restock' => 'Consumables',
                        'petty_cash'          => 'Petty Cash',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending_approval' => 'Pending',
                        'approved'         => 'Approved',
                        'rejected'         => 'Rejected',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Expense $record): bool =>
                        Auth::user()?->isIplAdmin() && $record->status === 'pending_approval'
                    )
                    ->requiresConfirmation()
                    ->action(function (Expense $record): void {
                        $record->update([
                            'status'      => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn(): bool => Auth::user()?->isIplAdmin()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit'   => Pages\EditExpense::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS,
        ]);
    }
}
