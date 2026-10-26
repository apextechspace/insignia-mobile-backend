<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\AssetResource\Pages;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Asset Register';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Asset Identity')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Asset Name / Description')
                            ->required()
                            ->placeholder('e.g. Samsung 55" UHD 4K Smart TV'),
                        Forms\Components\TextInput::make('asset_code')
                            ->label('Asset Tag / Barcode')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('AST-ATALA-TV01')
                            ->helperText('Unique physical barcode tag applied to the item.'),
                        Forms\Components\Select::make('room_id')
                            ->label('Assigned Suite')
                            ->relationship('room', 'name')
                            ->placeholder('Central Storage / Unassigned')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options([
                                'electronics'       => '📺 Electronics & Audio-Visual',
                                'furniture'         => '🪑 Furniture & Bedding',
                                'kitchen_appliance' => '🍳 Kitchen Appliances',
                                'plumbing'          => '🚿 Plumbing & Sanitary',
                                'lighting'          => '💡 Lighting & Electrical',
                                'hvac'              => '❄️ HVAC / Air Conditioning',
                                'other'             => '📦 Other Equipment',
                            ])
                            ->required()
                            ->native(false),
                    ])->columns(2),

                Forms\Components\Section::make('Technical & Condition Details')
                    ->schema([
                        Forms\Components\TextInput::make('brand')
                            ->label('Brand / Manufacturer')
                            ->placeholder('e.g. Samsung, Daikin, LG'),
                        Forms\Components\TextInput::make('model_number')
                            ->label('Model Number')
                            ->placeholder('e.g. AU7000-55'),
                        Forms\Components\TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->placeholder('SN-98234-A01'),
                        Forms\Components\Select::make('condition')
                            ->label('Physical Condition')
                            ->options([
                                'brand_new'    => '✨ Brand New',
                                'good'         => '✅ Good / Pristine',
                                'fair'         => '⚠️ Fair (Minor Wear)',
                                'needs_repair' => '🔧 Needs Maintenance/Repair',
                                'condemned'    => '❌ Condemned / Write-Off',
                            ])
                            ->required()
                            ->default('good')
                            ->native(false),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Purchase / Commission Date')
                            ->native(false),
                        Forms\Components\FileUpload::make('photo_url')
                            ->label('Asset Photo / Proof of Condition')
                            ->image()
                            ->disk('public')
                            ->directory('asset-photos')
                            ->imageEditor()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Maintenance & Placement Notes')
                            ->rows(3)
                            ->placeholder('Mounting location, warranty information, service history...')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo_url')
                    ->label('')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?background=334155&color=fff&name=Asset'),
                Tables\Columns\TextColumn::make('asset_code')
                    ->label('Tag Code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Asset Description')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Asset $record): string => ($record->brand ? $record->brand . ' ' : '') . ($record->model_number ?? '')),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Suite')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Storage')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'electronics' => 'Electronics',
                        'furniture' => 'Furniture',
                        'kitchen_appliance' => 'Kitchen',
                        'plumbing' => 'Plumbing',
                        'lighting' => 'Lighting',
                        'hvac' => 'HVAC',
                        default => ucfirst($state),
                    })
                    ->color('info'),
                Tables\Columns\TextColumn::make('condition')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'brand_new', 'good' => 'success',
                        'fair' => 'warning',
                        'needs_repair', 'condemned' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Inspected')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('room_id')
                    ->label('Filter by Suite')
                    ->relationship('room', 'name'),
                SelectFilter::make('condition')
                    ->options([
                        'brand_new' => 'Brand New',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'needs_repair' => 'Needs Repair',
                        'condemned' => 'Condemned',
                    ]),
                SelectFilter::make('category')
                    ->options([
                        'electronics' => 'Electronics',
                        'furniture' => 'Furniture',
                        'kitchen_appliance' => 'Kitchen Appliance',
                        'plumbing' => 'Plumbing',
                        'lighting' => 'Lighting',
                        'hvac' => 'HVAC',
                    ]),
            ])
            ->actions([
                Action::make('updateCondition')
                    ->label('Update Condition')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('condition')
                            ->label('New Condition')
                            ->options([
                                'brand_new' => '✨ Brand New',
                                'good' => '✅ Good / Pristine',
                                'fair' => '⚠️ Fair',
                                'needs_repair' => '🔧 Needs Repair',
                                'condemned' => '❌ Condemned',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Textarea::make('inspection_notes')
                            ->label('Inspection Observation')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Asset $record, array $data): void {
                        $record->update([
                            'condition' => $data['condition'],
                            'notes' => trim(($record->notes ? $record->notes . "\n" : '') . '[' . now()->format('Y-m-d H:i') . ' by ' . Auth::user()->name . ']: ' . $data['inspection_notes']),
                        ]);
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view'   => Pages\ViewAsset::route('/{record}'),
            'edit'   => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
