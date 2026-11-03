<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\StaffTaskResource\Pages;
use App\Models\StaffTask;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StaffTaskResource extends Resource
{
    protected static ?string $model = StaffTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Tasks';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Task Details')
                    ->schema([
                        Forms\Components\TextInput::make('task_title')
                            ->disabled(fn (): bool => Auth::user()?->isStaff())
                            ->dehydrated(fn (): bool => ! Auth::user()?->isStaff())
                            ->label('Task Title')
                            ->required()
                            ->placeholder('e.g. Turnaround clean – Junonia'),
                        Forms\Components\Select::make('task_type')
                            ->disabled(fn (): bool => Auth::user()?->isStaff())
                            ->dehydrated(fn (): bool => ! Auth::user()?->isStaff())
                            ->label('Task Type')
                            ->options([
                                'room_turnaround' => '🧹 Room Turnaround',
                                'daily_refresh' => '✨ Daily Cleaning / Refresh',
                                'maintenance_fix' => '🔧 Maintenance Fix',
                                'guest_delivery' => '📦 Guest Delivery',
                                'inspection' => '🔍 Inspection',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('priority')
                            ->disabled(fn (): bool => Auth::user()?->isStaff())
                            ->dehydrated(fn (): bool => ! Auth::user()?->isStaff())
                            ->label('Priority')
                            ->options([
                                'low' => '🟢 Low',
                                'medium' => '🟡 Medium',
                                'high' => '🔴 High',
                                'urgent' => '🚨 Urgent',
                            ])
                            ->default('medium')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('location_type')
                            ->label('Location Type')->options(['room' => 'Guest Room', 'property_area' => 'Property Area', 'general' => 'General Task'])
                            ->default('general')->required()->live()->dehydrated(false)
                            ->disabled(fn (): bool => Auth::user()?->isStaff())
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?StaffTask $record): void {
                                $component->state($record?->property_area_id ? 'property_area' : ($record?->room_id ? 'room' : 'general'));
                            })
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                                if ($state !== 'room') {
                                    $set('room_id', null);
                                }
                                if ($state !== 'property_area') {
                                    $set('property_area_id', null);
                                }
                                if ($state === 'property_area') {
                                    $set('task_type', 'daily_refresh');
                                }
                            }),
                        Forms\Components\Select::make('room_id')
                            ->disabled(fn (): bool => Auth::user()?->isStaff())
                            ->dehydrated(fn (): bool => ! Auth::user()?->isStaff())
                            ->label('Room')
                            ->relationship('room', 'name')
                            ->visible(fn (Forms\Get $get): bool => $get('location_type') === 'room')
                            ->required(fn (Forms\Get $get): bool => $get('location_type') === 'room')
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('property_area_id')
                            ->label('Property Area')
                            ->relationship('propertyArea', 'name', modifyQueryUsing: fn (Builder $query, ?StaffTask $record) => $query->where(fn (Builder $q) => $q->where('is_active', true)->when($record?->property_area_id, fn (Builder $q, $id) => $q->orWhere('property_areas.id', $id))))
                            ->visible(fn (Forms\Get $get): bool => $get('location_type') === 'property_area')
                            ->required(fn (Forms\Get $get): bool => $get('location_type') === 'property_area')
                            ->disabled(fn (): bool => Auth::user()?->isStaff())
                            ->dehydrated(fn (): bool => ! Auth::user()?->isStaff())
                            ->searchable()->preload()->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set): void {
                                if (empty($get('checklist_items'))) {
                                    $set('checklist_items', StaffTask::areaCleaningChecklist());
                                }
                            }),
                        Forms\Components\Select::make('user_id')
                            ->disabled(fn (): bool => Auth::user()?->isStaff())
                            ->dehydrated(fn (): bool => ! Auth::user()?->isStaff())
                            ->label('Assign To')
                            ->options(fn () => User::whereIn('role', [
                                User::ROLE_STAFF,
                                User::ROLE_GUEST_RELATIONS,
                                User::ROLE_DELIVERY_PARTNER,
                            ])->pluck('name', 'id'))
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->disabled(fn (): bool => Auth::user()?->isStaff())
                            ->dehydrated(fn (): bool => ! Auth::user()?->isStaff())
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                // Verification and room release are supervisor-only actions.

                            ])
                            ->default('pending')
                            ->native(false),
                    ])->columns(2),
                Forms\Components\Section::make('Checklist & Notes')
                    ->schema([
                        Forms\Components\Repeater::make('checklist_items')
                            ->label('Checklist Items')
                            ->defaultItems(0)
                            ->addable(fn (): bool => ! Auth::user()?->isStaff())
                            ->deletable(fn (): bool => ! Auth::user()?->isStaff())
                            ->reorderable(fn (): bool => ! Auth::user()?->isStaff())
                            ->schema([
                                Forms\Components\TextInput::make('item')
                                    ->label('Task Item')
                                    ->disabled(fn (): bool => Auth::user()?->isStaff())
                                    ->dehydrated()
                                    ->required()
                                    ->placeholder('e.g. Change bedsheets'),
                                Forms\Components\Toggle::make('done')
                                    ->label('Done')
                                    ->default(false),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $user = Auth::user();
                // Staff only see tasks assigned to them
                if ($user?->isStaff()) {
                    $query->where('user_id', $user->id);
                }

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('task_title')
                    ->label('Task')
                    ->searchable()
                    ->weight('medium')
                    ->limit(45),
                Tables\Columns\BadgeColumn::make('task_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\BadgeColumn::make('priority')
                    ->label('Priority')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => fn (string $state): bool => in_array($state, ['high', 'urgent']),
                    ]),
                Tables\Columns\TextColumn::make('location_name')
                    ->label('Location')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'in_progress',
                        'success' => fn (string $state): bool => in_array($state, ['completed', 'verified']),
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('verifiedBy.name')->label('Verified By')->placeholder('Not verified')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('verified_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('property_area_id')->label('Property Area')->relationship('propertyArea', 'name'),
                SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'in_progress' => 'In Progress',
                    'completed' => 'Completed', 'verified' => 'Verified',
                ]),
                SelectFilter::make('priority')->options([
                    'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent',
                ]),
            ])
            ->actions([
                Action::make('mark_complete')
                    ->label('Mark Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StaffTask $record): bool => in_array($record->status, ['pending', 'in_progress'])
                        && Auth::id() === $record->user_id
                    )
                    ->requiresConfirmation()
                    ->action(function (StaffTask $record): void {
                        try {
                            $record->update([
                                'status' => 'completed',
                                'completed_at' => now(),
                            ]);
                        } catch (\Illuminate\Validation\ValidationException $exception) {
                            \Filament\Notifications\Notification::make()->danger()->title('Checklist incomplete')->body(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
                Action::make('verify_area')->label('Verify Area Duty')->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                    ->visible(fn (StaffTask $record): bool => self::canCreate() && (bool) $record->property_area_id && $record->status === 'completed')
                    ->action(function (StaffTask $record): void {
                        try {
                            app(\App\Services\PropertyAreaTaskService::class)->verify($record, Auth::user());
                            \Filament\Notifications\Notification::make()->success()->title('Area duty verified')->send();
                        } catch (\Illuminate\Validation\ValidationException $exception) {
                            \Filament\Notifications\Notification::make()->danger()->title('Unable to verify')->body(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => ! Auth::user()?->isStaff()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffTasks::route('/'),
            'create' => Pages\CreateStaffTask::route('/create'),
            'edit' => Pages\EditStaffTask::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['room', 'propertyArea', 'assignedUser', 'verifiedBy']);
        if (Auth::user()?->isStaff()) {
            $query->where('user_id', Auth::id());
        }

        return $query;
    }

    public static function canView($record): bool
    {
        return Auth::check() && (! Auth::user()->isStaff()
            || (int) $record->user_id === (int) Auth::id());
    }

    public static function canCreate(): bool
    {
        return Auth::check() && ! Auth::user()->isStaff();
    }

    public static function canEdit($record): bool
    {
        return Auth::check() && (! Auth::user()->isStaff() || (int) $record->user_id === (int) Auth::id());
    }

    public static function canDelete($record): bool
    {
        return Auth::check() && ! Auth::user()->isStaff();
    }

    public static function canViewAny(): bool
    {
        return Auth::check();
    }
}
