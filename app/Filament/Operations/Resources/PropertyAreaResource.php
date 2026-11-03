<?php

namespace App\Filament\Operations\Resources;

use App\Filament\Operations\Resources\PropertyAreaResource\Pages;
use App\Models\PropertyArea;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PropertyAreaResource extends Resource
{
    protected static ?string $model = PropertyArea::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Property Areas';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return StaffTaskResource::canCreate();
    }

    public static function canCreate(): bool
    {
        return StaffTaskResource::canCreate();
    }

    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return self::canCreate();
    }

    public static function canDelete($record): bool
    {
        return self::canCreate() && ! $record->tasks()->exists();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(150)->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('description')->label('Location / cleaning instructions')->maxLength(2000)->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->label('Available for new duties')->default(true)
                ->helperText('Turn this off to retire an area while keeping its task history.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('description')->limit(70)->placeholder('No additional instructions'),
            Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            Tables\Columns\TextColumn::make('tasks_count')->counts('tasks')->label('Duties assigned'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPropertyAreas::route('/'), 'create' => Pages\CreatePropertyArea::route('/create'), 'edit' => Pages\EditPropertyArea::route('/{record}/edit')];
    }
}
