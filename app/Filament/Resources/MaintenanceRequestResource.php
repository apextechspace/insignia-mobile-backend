<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceRequestResource\Pages;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\ExpoPushService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceRequestResource extends Resource
{
    protected static ?string $model = MaintenanceRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Hospitality';

    protected static ?string $navigationLabel = 'Maintenance';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->label('Resident')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('category')
                ->options([
                    'AC'          => 'Air Conditioning',
                    'Plumbing'    => 'Plumbing',
                    'Electrical'  => 'Electrical',
                    'Internet'    => 'Internet / WiFi',
                    'Furniture'   => 'Furniture',
                    'Cleaning'    => 'Cleaning',
                    'General'     => 'General',
                ])
                ->required(),
            Forms\Components\TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            Forms\Components\Textarea::make('description')->required()->columnSpanFull(),
            Forms\Components\Select::make('priority')
                ->options(['low' => 'Low', 'normal' => 'Normal', 'urgent' => 'Urgent'])
                ->default('normal')
                ->required(),
            Forms\Components\Select::make('status')
                ->options([
                    'open'        => 'Open',
                    'in-progress' => 'In Progress',
                    'resolved'    => 'Resolved',
                    'closed'      => 'Closed',
                ])
                ->default('open')
                ->required()
                ->live()
                ->afterStateUpdated(function ($record, $state) {
                    if (!$record) return;
                    $user = $record->user;
                    if ($user && $user->expo_push_token) {
                        $statusLabel = match ($state) {
                            'in-progress' => 'In Progress',
                            'resolved'    => 'Resolved ✅',
                            'closed'      => 'Closed',
                            default       => ucfirst($state),
                        };
                        ExpoPushService::send(
                            $user->expo_push_token,
                            '🔧 Maintenance Update',
                            "Your request \"{$record->title}\" is now {$statusLabel}.",
                            ['type' => 'maintenance', 'id' => $record->id]
                        );
                    }
                }),
            Forms\Components\Textarea::make('admin_notes')
                ->label('Internal Notes (not sent to resident)')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Resident')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'normal' => 'warning',
                        'low'    => 'gray',
                        default  => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open'        => 'warning',
                        'in-progress' => 'info',
                        'resolved'    => 'success',
                        'closed'      => 'gray',
                        default       => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open'        => 'Open',
                        'in-progress' => 'In Progress',
                        'resolved'    => 'Resolved',
                        'closed'      => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options(['low' => 'Low', 'normal' => 'Normal', 'urgent' => 'Urgent']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMaintenanceRequests::route('/'),
            'create' => Pages\CreateMaintenanceRequest::route('/create'),
            'edit'   => Pages\EditMaintenanceRequest::route('/{record}/edit'),
        ];
    }
}
