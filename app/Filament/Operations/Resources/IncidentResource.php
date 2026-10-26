<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\IncidentResource\Pages;
use App\Models\Incident;
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
use Illuminate\Support\Str;

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Incidents';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Incident Report')
                    ->schema([
                        Forms\Components\TextInput::make('incident_code')
                            ->label('Incident Code')
                            ->disabled()
                            ->placeholder('Auto-generated')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('title')
                            ->label('Incident Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Brief one-line summary'),
                        Forms\Components\Select::make('type')
                            ->label('Incident Type')
                            ->options([
                                'property_damage'    => 'Property Damage',
                                'missing_item'       => 'Missing Item',
                                'maintenance_failure' => 'Maintenance Failure',
                                'security_concern'   => 'Security Concern',
                                'guest_complaint'    => 'Guest Complaint',
                                'other'              => 'Other',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('severity')
                            ->label('Severity')
                            ->options([
                                'low'      => '🟢 Low',
                                'medium'   => '🟡 Medium',
                                'high'     => '🔴 High',
                                'critical' => '🚨 Critical',
                            ])
                            ->default('medium')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('room_id')
                            ->label('Affected Room')
                            ->relationship('room', 'name')
                            ->searchable()
                            ->placeholder('Select room (if applicable)')
                            ->native(false),
                        Forms\Components\Select::make('assigned_to')
                            ->label('Assign To')
                            ->options(fn() => User::whereIn('role', [
                                User::ROLE_DELIVERY_PARTNER,
                                User::ROLE_GUEST_RELATIONS,
                                User::ROLE_STAFF,
                            ])->pluck('name', 'id'))
                            ->searchable()
                            ->native(false),
                    ])->columns(2),
                Forms\Components\Section::make('Description & Evidence')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Full Description')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('immediate_action_taken')
                            ->label('Immediate Action Taken')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('evidence_photo_1')
                            ->label('Evidence Photo 1')
                            ->image()
                            ->imageEditor()
                            ->directory('incident-evidence'),
                        Forms\Components\FileUpload::make('evidence_photo_2')
                            ->label('Evidence Photo 2')
                            ->image()
                            ->imageEditor()
                            ->directory('incident-evidence'),
                    ]),
                Forms\Components\Section::make('Resolution')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'reported'             => 'Reported',
                                'under_investigation'  => 'Under Investigation',
                                'awaiting_parts'       => 'Awaiting Parts',
                                'escalated_to_ipl'     => 'Escalated to IPL',
                                'resolved'             => 'Resolved',
                                'closed'               => 'Closed',
                            ])
                            ->native(false),
                        Forms\Components\Toggle::make('is_escalated_to_ipl')
                            ->label('Escalate to IPL Management')
                            ->helperText('Flag this for IPL\'s attention without transferring operational responsibility'),
                        Forms\Components\Textarea::make('ipl_escalation_notes')
                            ->label('IPL Escalation Notes')
                            ->rows(3)
                            ->visible(fn(Forms\Get $get): bool => $get('is_escalated_to_ipl'))
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('resolution_summary')
                            ->label('Resolution Summary')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('associated_cost')
                            ->label('Associated Cost (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('incident_code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Incident')
                    ->searchable()
                    ->limit(45)
                    ->weight('medium'),
                Tables\Columns\BadgeColumn::make('severity')
                    ->label('Severity')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger'  => 'high',
                        'danger'  => 'critical',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'low'      => '🟢 Low',
                        'medium'   => '🟡 Medium',
                        'high'     => '🔴 High',
                        'critical' => '🚨 Critical',
                        default    => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Room')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'reported',
                        'warning' => 'under_investigation',
                        'warning' => 'awaiting_parts',
                        'danger'  => 'escalated_to_ipl',
                        'success' => 'resolved',
                        'success' => 'closed',
                    ])
                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\IconColumn::make('is_escalated_to_ipl')
                    ->label('IPL Escalated')
                    ->boolean(),
                Tables\Columns\TextColumn::make('reportedBy.name')
                    ->label('Reported By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reported')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('severity')->options([
                    'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical',
                ]),
                SelectFilter::make('status')->options([
                    'reported' => 'Reported', 'under_investigation' => 'Investigating',
                    'escalated_to_ipl' => 'Escalated', 'resolved' => 'Resolved',
                ]),
            ])
            ->actions([
                Action::make('mark_resolved')
                    ->label('Mark Resolved')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn(Incident $record): bool =>
                        !in_array($record->status, ['resolved', 'closed'])
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('resolution_summary')
                            ->label('Resolution Summary')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Incident $record, array $data): void {
                        $record->update([
                            'status'             => 'resolved',
                            'resolution_summary' => $data['resolution_summary'],
                            'resolved_by'        => Auth::id(),
                            'resolved_at'        => now(),
                        ]);
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListIncidents::route('/'),
            'create' => Pages\CreateIncident::route('/create'),
            'edit'   => Pages\EditIncident::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS, User::ROLE_STAFF,
        ]);
    }
}
