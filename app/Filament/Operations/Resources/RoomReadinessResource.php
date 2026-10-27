<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\RoomReadinessResource\Pages;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomTurnoverService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RoomReadinessResource extends Resource
{
    protected static ?string $model = Room::class;
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationLabel = 'Room Readiness Review';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function canViewAny(): bool
    {
        return in_array(Auth::user()?->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS,
        ], true);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('status', 'cleaning');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Suite')->sortable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('housekeeping_notes')->limit(60),
        ])->actions([
            Tables\Actions\Action::make('release')
                ->label('Verify & Release')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Verify the completed housekeeping checklist')
                ->modalDescription('The latest room turnaround task must be marked completed, every checklist item checked, and no guest checked in.')
                ->action(fn (Room $record) => app(RoomTurnoverService::class)->markReady($record, Auth::user())),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRoomReadiness::route('/')];
    }
}
