<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\StaffTaskResource\Pages;
use App\Models\StaffTask;
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
                            ->label('Task Title')
                            ->required()
                            ->placeholder('e.g. Turnaround clean – Junonia'),
                        Forms\Components\Select::make('task_type')
                            ->label('Task Type')
                            ->options([
                                'room_turnaround' => '🧹 Room Turnaround',
                                'daily_refresh'   => '✨ Daily Refresh',
                                'maintenance_fix' => '🔧 Maintenance Fix',
                                'guest_delivery'  => '📦 Guest Delivery',
                                'inspection'      => '🔍 Inspection',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('priority')
                            ->label('Priority')
                            ->options([
                                'low'    => '🟢 Low',
                                'medium' => '🟡 Medium',
                                'high'   => '🔴 High',
                                'urgent' => '🚨 Urgent',
                            ])
                            ->default('medium')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('room_id')
                            ->label('Room')
                            ->relationship('room', 'name')
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('user_id')
                            ->label('Assign To')
                            ->options(fn() => User::whereIn('role', [
                                User::ROLE_STAFF,
                                User::ROLE_GUEST_RELATIONS,
                                User::ROLE_DELIVERY_PARTNER,
                            ])->pluck('name', 'id'))
                            ->searchable()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending'     => 'Pending',
                                'in_progress' => 'In Progress',
                                'completed'   => 'Completed',
                                'verified'    => 'Verified',
                            ])
                            ->default('pending')
                            ->native(false),
                    ])->columns(2),
                Forms\Components\Section::make('Checklist & Notes')
                    ->schema([
                        Forms\Components\Repeater::make('checklist_items')
                            ->label('Checklist Items')
                            ->schema([
                                Forms\Components\TextInput::make('item')
                                    ->label('Task Item')
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
                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\BadgeColumn::make('priority')
                    ->label('Priority')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger'  => fn(string $state): bool => in_array($state, ['high', 'urgent']),
                    ]),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Room')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'in_progress',
                        'success' => fn(string $state): bool => in_array($state, ['completed', 'verified']),
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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
                    ->visible(fn(StaffTask $record): bool =>
                        in_array($record->status, ['pending', 'in_progress'])
                        && Auth::id() === $record->user_id
                    )
                    ->requiresConfirmation()
                    ->action(function (StaffTask $record): void {
                        $record->update([
                            'status'       => 'completed',
                            'completed_at' => now(),
                        ]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn(): bool => !Auth::user()?->isStaff()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStaffTasks::route('/'),
            'create' => Pages\CreateStaffTask::route('/create'),
            'edit'   => Pages\EditStaffTask::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return Auth::check();
    }
}
