<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\StaffShiftResource\Pages;
use App\Models\StaffShift;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StaffShiftResource extends Resource
{
    protected static ?string $model = StaffShift::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Duty Roster';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 8;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Staff members only see their own assigned shifts
        if (Auth::user()?->isStaff()) {
            $query->where('user_id', Auth::id());
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shift Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Staff Member')
                            ->options(
                                User::whereIn('role', [
                                    User::ROLE_STAFF,
                                    User::ROLE_GUEST_RELATIONS,
                                    User::ROLE_DELIVERY_PARTNER,
                                ])->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->native(false),
                        Forms\Components\DatePicker::make('shift_date')
                            ->label('Shift Date')
                            ->default(now())
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('shift_type')
                            ->label('Shift Type')
                            ->options([
                                'morning'  => '🌅 Morning Shift (07:00 - 15:30)',
                                'evening'  => '🌆 Evening Shift (15:00 - 23:30)',
                                'night'    => '🌙 Night Shift (23:00 - 07:30)',
                                'full_day' => '☀️ Full Day Coverage (08:00 - 18:00)',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Shift Status')
                            ->options([
                                'scheduled' => '📅 Scheduled',
                                'present'   => '🟢 Present (On Duty)',
                                'late'      => '⏳ Late Arrival',
                                'absent'    => '🔴 Absent',
                                'completed' => '✅ Completed / Handed Over',
                            ])
                            ->default('scheduled')
                            ->required()
                            ->native(false),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Actual Clock-in Time'),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('Actual Clock-out Time'),
                    ])->columns(2),

                Forms\Components\Section::make('Shift Handover & Operations Log')
                    ->schema([
                        Forms\Components\Textarea::make('handover_notes')
                            ->label('Shift Handover Notes')
                            ->rows(4)
                            ->placeholder('Record pending check-outs, guest requests, maintenance flags, or issues to pass to the next shift...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Staff Member')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shift_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shift_type')
                    ->label('Roster Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'morning' => 'Morning',
                        'evening' => 'Evening',
                        'night' => 'Night',
                        'full_day' => 'Full Day',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'morning' => 'info',
                        'evening' => 'warning',
                        'night' => 'purple',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'scheduled' => 'info',
                        'late' => 'warning',
                        'absent' => 'danger',
                        'completed' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Clock-In')
                    ->time('H:i')
                    ->placeholder('--:--'),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Clock-Out')
                    ->time('H:i')
                    ->placeholder('--:--'),
                Tables\Columns\TextColumn::make('handover_notes')
                    ->label('Handover Summary')
                    ->limit(40)
                    ->tooltip(fn (StaffShift $record): ?string => $record->handover_notes),
            ])
            ->defaultSort('shift_date', 'desc')
            ->filters([
                SelectFilter::make('shift_type')
                    ->options([
                        'morning' => 'Morning',
                        'evening' => 'Evening',
                        'night' => 'Night',
                        'full_day' => 'Full Day',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'present' => 'Present',
                        'late' => 'Late',
                        'absent' => 'Absent',
                        'completed' => 'Completed',
                    ]),
            ])
            ->actions([
                // 1-Click Clock In
                Action::make('clockIn')
                    ->label('Clock In')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StaffShift $record): bool =>
                        $record->status === 'scheduled' &&
                        ($record->user_id === Auth::id() || Auth::user()?->isIplAdmin() || Auth::user()?->isDeliveryPartner())
                    )
                    ->action(function (StaffShift $record): void {
                        $record->update([
                            'status' => 'present',
                            'start_time' => now()->format('H:i:s'),
                        ]);

                        Notification::make()
                            ->title('Checked In on Duty')
                            ->success()
                            ->send();
                    }),

                // Complete Shift with Handover Modal
                Action::make('handover')
                    ->label('Complete Shift')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn (StaffShift $record): bool =>
                        in_array($record->status, ['present', 'late']) &&
                        ($record->user_id === Auth::id() || Auth::user()?->isIplAdmin() || Auth::user()?->isDeliveryPartner())
                    )
                    ->form([
                        Forms\Components\Textarea::make('handover_notes')
                            ->label('Shift Handover Report')
                            ->required()
                            ->placeholder('Describe current status of apartments, key handovers, pending guest services...')
                            ->rows(4),
                    ])
                    ->action(function (StaffShift $record, array $data): void {
                        $record->update([
                            'status' => 'completed',
                            'end_time' => now()->format('H:i:s'),
                            'handover_notes' => $data['handover_notes'],
                        ]);

                        Notification::make()
                            ->title('Shift Completed & Handover Saved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => Auth::user()?->isIplAdmin() || Auth::user()?->isDeliveryPartner()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStaffShifts::route('/'),
            'create' => Pages\CreateStaffShift::route('/create'),
            'edit'   => Pages\EditStaffShift::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        return $user && ($user->isIplAdmin() || $user->isDeliveryPartner());
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();
        return $user && ($user->isIplAdmin() || $user->isDeliveryPartner());
    }

    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();
        return $user && ($user->isIplAdmin() || $user->isDeliveryPartner());
    }

    public static function canViewAny(): bool
    {
        return Auth::check();
    }
}
