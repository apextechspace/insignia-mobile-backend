<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\AssetHandoverResource\Pages;
use App\Models\AssetHandover;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

class AssetHandoverResource extends Resource
{
    protected static ?string $model = AssetHandover::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Handover Protocols';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Handover Protocol Header')
                    ->schema([
                        Forms\Components\Select::make('room_id')
                            ->label('Apartment Suite')
                            ->relationship('room', 'name')
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('handover_date')
                            ->label('Protocol Date')
                            ->default(now())
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending_mcl_review'      => '⏳ Pending MCL Physical Verification',
                                'verified_by_mcl'         => '📝 Verified & Signed by MCL',
                                'locked_and_signed_by_ipl' => '🔒 Locked & Countersigned by IPL',
                            ])
                            ->default('pending_mcl_review')
                            ->disabled(fn () => !Auth::user()->isIplAdmin())
                            ->required()
                            ->native(false),
                    ])->columns(3),

                Forms\Components\Section::make('Physical Condition & Joint Inventory Notes')
                    ->schema([
                        Forms\Components\Textarea::make('condition_notes')
                            ->label('Inspection Assessment & Remarks')
                            ->required()
                            ->rows(4)
                            ->placeholder('Record exact state of furniture, electronics, keys handed over, meter readings...'),
                        Forms\Components\FileUpload::make('signed_document_pdf')
                            ->label('Counter-Signed Protocol Document (PDF / Scan)')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->disk('public')
                            ->directory('handover-documents')
                            ->openable()
                            ->downloadable(),
                    ]),

                Forms\Components\Section::make('Digital Signatures & Audit Trail')
                    ->schema([
                        Forms\Components\Select::make('mcl_representative_id')
                            ->label('MCL Signatory (Facility Operator)')
                            ->relationship('mclRepresentative', 'name')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('mcl_signed_at')
                            ->label('MCL Timestamp')
                            ->disabled(),
                        Forms\Components\Select::make('ipl_representative_id')
                            ->label('IPL Signatory (Property Owner)')
                            ->relationship('iplRepresentative', 'name')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('ipl_signed_at')
                            ->label('IPL Timestamp')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Suite')
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('handover_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending_mcl_review' => 'Pending Verification',
                        'verified_by_mcl' => 'Signed by MCL',
                        'locked_and_signed_by_ipl' => 'Locked by IPL',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending_mcl_review' => 'warning',
                        'verified_by_mcl' => 'info',
                        'locked_and_signed_by_ipl' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('mclRepresentative.name')
                    ->label('MCL Rep')
                    ->placeholder('Unsigned'),
                Tables\Columns\TextColumn::make('mcl_signed_at')
                    ->label('MCL Signed')
                    ->dateTime('M d, H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('iplRepresentative.name')
                    ->label('IPL Rep')
                    ->placeholder('Pending Countersign'),
                Tables\Columns\TextColumn::make('ipl_signed_at')
                    ->label('IPL Signed')
                    ->dateTime('M d, H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('room_id')
                    ->label('Apartment Suite')
                    ->relationship('room', 'name'),
                SelectFilter::make('status')
                    ->options([
                        'pending_mcl_review' => 'Pending MCL Verification',
                        'verified_by_mcl' => 'Verified & Signed by MCL',
                        'locked_and_signed_by_ipl' => 'Locked & Signed by IPL',
                    ]),
            ])
            ->actions([
                // MCL Verify & Sign
                Action::make('signMcl')
                    ->label('MCL Sign')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->visible(fn (AssetHandover $record): bool =>
                        $record->status === 'pending_mcl_review' &&
                        (Auth::user()->isDeliveryPartner() || Auth::user()->isIplAdmin())
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Physical Asset Verification')
                    ->modalDescription('By signing, you confirm that all suite assets, electronics, and fittings have been inspected in person and match inventory records.')
                    ->action(function (AssetHandover $record): void {
                        $record->update([
                            'status' => 'verified_by_mcl',
                            'mcl_representative_id' => Auth::id(),
                            'mcl_signed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Protocol Verified & Signed by MCL')
                            ->success()
                            ->send();
                    }),

                // IPL Countersign & Lock
                Action::make('signIpl')
                    ->label('IPL Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('success')
                    ->visible(fn (AssetHandover $record): bool =>
                        $record->status === 'verified_by_mcl' &&
                        Auth::user()->isIplAdmin()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Countersign & Lock Handover Protocol')
                    ->modalDescription('Locking this protocol establishes an immutable baseline for this apartment suite.')
                    ->action(function (AssetHandover $record): void {
                        $record->update([
                            'status' => 'locked_and_signed_by_ipl',
                            'ipl_representative_id' => Auth::id(),
                            'ipl_signed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Protocol Formally Countersigned & Locked')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (AssetHandover $record): bool =>
                        $record->status !== 'locked_and_signed_by_ipl' || Auth::user()->isIplAdmin()
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAssetHandovers::route('/'),
            'create' => Pages\CreateAssetHandover::route('/create'),
            'view'   => Pages\ViewAssetHandover::route('/{record}'),
            'edit'   => Pages\EditAssetHandover::route('/{record}/edit'),
        ];
    }
}
