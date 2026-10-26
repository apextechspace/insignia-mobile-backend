<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\PettyCashResource\Pages;
use App\Models\PettyCashTransaction;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

class PettyCashResource extends Resource
{
    protected static ?string $model = PettyCashTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Operating Float';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        // Housekeeping / Floor staff have zero access to financial ledgers
        return !Auth::user()->isStaff();
    }

    public static function form(Form $form): Form
    {
        $latestBalance = PettyCashTransaction::latest('id')->value('balance_after') ?? 0;

        return $form
            ->schema([
                Forms\Components\Section::make('Operating Float Transaction')
                    ->description('Current operational float / imprest balance: ₦' . number_format($latestBalance, 2))
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Transaction Type')
                            ->options([
                                'replenishment' => '📥 Float Top-up / Allocation',
                                'disbursement'  => '📤 Direct Disbursement / Payout',
                            ])
                            ->required()
                            ->reactive()
                            ->native(false),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->required()
                            ->minValue(100),
                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Transaction Date & Time')
                            ->default(now())
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('purpose')
                            ->label('Detailed Purpose / Beneficiary')
                            ->required()
                            ->placeholder('e.g. Purchase of replacement faucet washers for Lyria suite')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Audit Verification & Receipt')
                    ->schema([
                        Forms\Components\FileUpload::make('receipt_photo')
                            ->label('Physical Paper Receipt / Cash Voucher')
                            ->image()
                            ->disk('public')
                            ->directory('petty-cash-receipts')
                            ->imageEditor()
                            ->openable()
                            ->downloadable(),
                        Forms\Components\Select::make('handled_by')
                            ->label('Handler / Custodian')
                            ->options(User::pluck('name', 'id'))
                            ->default(fn () => Auth::id())
                            ->disabled(fn () => !Auth::user()->isIplAdmin())
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('approved_by')
                            ->label('Authorizing Manager')
                            ->options(User::whereIn('role', [User::ROLE_IPL_ADMIN, User::ROLE_ADMIN, User::ROLE_DELIVERY_PARTNER])->pluck('name', 'id'))
                            ->default(fn () => Auth::id())
                            ->native(false),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date & Time')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'replenishment' => 'Replenish (In)',
                        'disbursement'  => 'Disburse (Out)',
                        default         => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'replenishment' => 'success',
                        'disbursement'  => 'danger',
                        default         => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('NGN')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Running Safe Balance')
                    ->money('NGN')
                    ->color('info')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('purpose')
                    ->label('Purpose / Item')
                    ->searchable()
                    ->limit(45),
                Tables\Columns\ImageColumn::make('receipt_photo')
                    ->label('Voucher')
                    ->disk('public')
                    ->circular(),
                Tables\Columns\TextColumn::make('handler.name')
                    ->label('Handled By'),
                Tables\Columns\TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'replenishment' => 'Float Replenishment',
                        'disbursement'  => 'Disbursement',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPettyCashTransactions::route('/'),
            'create' => Pages\CreatePettyCashTransaction::route('/create'),
            'view'   => Pages\ViewPettyCashTransaction::route('/{record}'),
        ];
    }
}
