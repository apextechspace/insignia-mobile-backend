<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\MonthlyStatementResource\Pages;
use App\Models\MonthlyStatement;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class MonthlyStatementResource extends Resource
{
    protected static ?string $model = MonthlyStatement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Monthly Statements';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;

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
                            ->options(array_combine(range(2025, 2030), range(2025, 2030)))
                            ->default(date('Y'))
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
                            ->default((int)date('n'))
                            ->required()
                            ->native(false),
                    ])->columns(3),
                Forms\Components\Section::make('Financial Summary')
                    ->schema([
                        Forms\Components\TextInput::make('total_accommodation_revenue')
                            ->label('Total Accommodation Revenue (₦)')
                            ->numeric()->prefix('₦')->default(0),
                        Forms\Components\TextInput::make('total_permitted_expenses')
                            ->label('Total Permitted Expenses (₦)')
                            ->numeric()->prefix('₦')->default(0),
                        Forms\Components\TextInput::make('management_fee_percentage')
                            ->label('Management Fee (%)')
                            ->numeric()->suffix('%')->default(0),
                        Forms\Components\TextInput::make('management_fee_amount')
                            ->label('Management Fee Amount (₦)')
                            ->numeric()->prefix('₦')->default(0),
                        Forms\Components\TextInput::make('net_payable_to_ipl')
                            ->label('Net Payable to IPL (₦)')
                            ->numeric()->prefix('₦')->default(0),
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
                            ->disabled(fn(): bool => !Auth::user()?->isIplAdmin())
                            ->native(false),
                        Forms\Components\Textarea::make('ipl_query_notes')
                            ->label('IPL Query Notes')
                            ->rows(3)
                            ->visible(fn(): bool => Auth::user()?->isIplAdmin())
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
                Action::make('submit_to_ipl')
                    ->label('Submit to IPL')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn(MonthlyStatement $record): bool =>
                        $record->status === 'draft' && Auth::user()?->isDeliveryPartner()
                    )
                    ->requiresConfirmation()
                    ->action(fn(MonthlyStatement $record) => $record->update([
                        'status'       => 'submitted_to_ipl',
                        'submitted_at' => now(),
                    ])),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn(MonthlyStatement $record): bool =>
                        Auth::user()?->isIplAdmin() && $record->status === 'submitted_to_ipl'
                    )
                    ->requiresConfirmation()
                    ->action(fn(MonthlyStatement $record) => $record->update([
                        'status'      => 'approved_by_ipl',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ])),
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
                    ->action(fn(MonthlyStatement $record, array $data) => $record->update([
                        'status'           => 'queried',
                        'ipl_query_notes'  => $data['ipl_query_notes'],
                    ])),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMonthlyStatements::route('/'),
            'create' => Pages\CreateMonthlyStatement::route('/create'),
            'edit'   => Pages\EditMonthlyStatement::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN, User::ROLE_DELIVERY_PARTNER,
        ]);
    }
}
