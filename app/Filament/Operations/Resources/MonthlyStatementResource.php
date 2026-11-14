<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\MonthlyStatementResource\Pages;
use App\Models\MonthlyStatement;
use App\Models\User;
use App\Services\MclStatementReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class MonthlyStatementResource extends Resource
{
    protected static ?string $model = MonthlyStatement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Monthly Statements';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return Auth::user()?->isDeliveryPartner() ? $query->where('submitted_by', Auth::id()) : $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Statement Period')
                    ->schema([
                        Forms\Components\TextInput::make('statement_code')
                            ->label('Statement Code')
                            ->disabled()
                            ->placeholder('e.g. STM-2026-10')
                            ->dehydrated(false),
                        Forms\Components\Select::make('year')
                            ->label('Year')
                            ->options(fn () => array_combine(range(2025, now(config('property.timezone'))->year), range(2025, now(config('property.timezone'))->year)))
                            ->default(fn () => now(config('property.timezone'))->year)
                            ->disabledOn('edit')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('month')
                            ->label('Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March',
                                4 => 'April', 5 => 'May', 6 => 'June',
                                7 => 'July', 8 => 'August', 9 => 'September',
                                10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->default(fn () => now(config('property.timezone'))->month)
                            ->disabledOn('edit')
                            ->required()
                            ->native(false),
                    ])->columns(3),
                Forms\Components\Section::make('Financial Summary')
                    ->schema([
                        Forms\Components\TextInput::make('management_fee_percentage')
                            ->label('Management Fee (%)')
                            ->helperText('Use the agreed rate. The fee is calculated on positive net accommodation receipts after refunds and adjustments.')
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0),
                        Forms\Components\TextInput::make('actual_float_balance')
                            ->label('Actual closing imprest balance (₦)')
                            ->numeric()->minValue(0)->maxValue(9999999999.99)
                            ->helperText('Use the bank or card balance at the close of the statement period.'),
                        Forms\Components\Textarea::make('float_reconciliation_notes')
                            ->label('Imprest reconciliation notes')->maxLength(4000),
                    ])->columns(2),
                Forms\Components\Section::make('Documents & Status')
                    ->schema([
                        Forms\Components\FileUpload::make('document_pdf')
                            ->label('Statement PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('monthly-statements'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft'            => 'Draft',
                                'submitted_to_ipl' => 'Submitted to IPL',
                                'queried'          => 'Queried by IPL',
                                'approved_by_ipl'  => 'Approved by IPL',
                                'settled'          => 'Settled',
                            ])
                            ->disabled()->dehydrated(false)
                            ->native(false),
                        Forms\Components\Textarea::make('ipl_query_notes')
                            ->label('IPL Query Notes')
                            ->rows(3)
                            ->disabled()->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('statement_code')
                    ->label('Statement')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('year')
                    ->label('Year'),
                Tables\Columns\TextColumn::make('month')
                    ->label('Month')
                    ->formatStateUsing(fn($state): string => is_numeric($state) && (int)$state >= 1 && (int)$state <= 12 ? date('F', mktime(0, 0, 0, (int)$state, 1)) : (string)$state),
                Tables\Columns\TextColumn::make('total_accommodation_revenue')
                    ->label('Revenue (₦)')
                    ->money('NGN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_permitted_expenses')
                    ->label('Expenses (₦)')
                    ->money('NGN'),
                Tables\Columns\TextColumn::make('management_fee_amount')
                    ->label('Mgmt Fee (₦)')
                    ->money('NGN'),
                Tables\Columns\TextColumn::make('net_payable_to_ipl')
                    ->label('Net to IPL (₦)')
                    ->money('NGN')
                    ->weight('bold')
                    ->color('success'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'draft',
                        'warning' => 'submitted_to_ipl',
                        'danger'  => 'queried',
                        'success' => fn(string $state): bool => in_array($state, ['approved_by_ipl', 'settled']),
                    ])
                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('submittedBy.name')
                    ->label('Submitted By')
                    ->visible(fn(): bool => Auth::user()?->isIplAdmin()),
            ])
            ->defaultSort('year', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label('Open Statement'),
                Action::make('submit_to_ipl')
                    ->label(fn (MonthlyStatement $record) => $record->status === 'queried' ? 'Resubmit to IPL' : 'Submit to IPL')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn(MonthlyStatement $record): bool =>
                        self::canEdit($record)
                    )
                    ->requiresConfirmation()
                    ->modalDescription('Submit the current transaction totals and imprest reconciliation for IPL review.')
                    ->action(function (MonthlyStatement $record): void {
                        DB::transaction(function () use ($record): void {
                            $statement = self::getEloquentQuery()->lockForUpdate()->findOrFail($record->id);
                            abort_unless(self::canEdit($statement), 403);
                            $report = app(MclStatementReport::class)->build($statement);
                            if (($report['opening'] || $report['topups'] || $report['spending'] || $report['returned'])
                                && ($report['actual'] === null || ($report['difference'] !== 0 && blank($statement->float_reconciliation_notes)))) {
                                \Filament\Notifications\Notification::make()->danger()
                                    ->title('Complete the imprest reconciliation first')
                                    ->body('Enter the actual closing balance and explain any difference.')->send();

                                return;
                            }
                            $statement->update([
                                'snapshot' => $report,
                                'total_accommodation_revenue' => $report['revenue'] / 100,
                                'total_permitted_expenses' => $report['expenseTotal'] / 100,
                                'management_fee_amount' => $report['fee'] / 100,
                                'net_payable_to_ipl' => $report['net'] / 100,
                                'status' => 'submitted_to_ipl',
                                'submitted_at' => now(),
                            ]);
                        });
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn(MonthlyStatement $record): bool =>
                        Auth::user()?->isIplAdmin() && $record->status === 'submitted_to_ipl'
                    )
                    ->requiresConfirmation()
                    ->action(function (MonthlyStatement $record): void {
                        abort_unless(Auth::user()?->isIplAdmin(), 403);
                        MonthlyStatement::whereKey($record->id)->where('status', 'submitted_to_ipl')->update([
                            'status' => 'approved_by_ipl',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                    }),
                Action::make('query')
                    ->label('Query Statement')
                    ->icon('heroicon-o-question-mark-circle')
                    ->color('danger')
                    ->visible(fn(MonthlyStatement $record): bool =>
                        Auth::user()?->isIplAdmin() && $record->status === 'submitted_to_ipl'
                    )
                    ->form([
                        Forms\Components\Textarea::make('ipl_query_notes')
                            ->label('What needs clarification?')
                            ->required()->rows(3),
                    ])
                    ->action(function (MonthlyStatement $record, array $data): void {
                        abort_unless(Auth::user()?->isIplAdmin(), 403);
                        MonthlyStatement::whereKey($record->id)->where('status', 'submitted_to_ipl')->update([
                            'status' => 'queried',
                            'ipl_query_notes' => $data['ipl_query_notes'],
                        ]);
                    }),
                Tables\Actions\EditAction::make(),
                Action::make('settle')
                    ->label('Mark Settled')
                    ->visible(fn (MonthlyStatement $record) => Auth::user()?->isIplAdmin() && $record->status === 'approved_by_ipl')
                    ->requiresConfirmation()
                    ->action(function (MonthlyStatement $record): void {
                        abort_unless(Auth::user()?->isIplAdmin(), 403);
                        MonthlyStatement::whereKey($record->id)
                            ->where('status', 'approved_by_ipl')->update(['status' => 'settled']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMonthlyStatements::route('/'),
            'create' => Pages\CreateMonthlyStatement::route('/create'),
            'edit'   => Pages\EditMonthlyStatement::route('/{record}/edit'),
            'view'   => Pages\ViewMonthlyStatement::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isIplAdmin() || Auth::user()?->isDeliveryPartner();
    }

    public static function canEdit($record): bool
    {
        if (Auth::user()?->isIplAdmin()) {
            return in_array($record->status, ['draft', 'queried'], true);
        }
        return Auth::user()?->isDeliveryPartner()
            && (int) $record->submitted_by === (int) Auth::id()
            && in_array($record->status, ['draft', 'queried'], true);
    }

    public static function canDelete($record): bool { return false; }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN, User::ROLE_DELIVERY_PARTNER,
        ]);
    }
}
