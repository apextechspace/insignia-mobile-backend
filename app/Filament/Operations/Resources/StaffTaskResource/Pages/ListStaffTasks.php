<?php

namespace App\Filament\Operations\Resources\StaffTaskResource\Pages;

use App\Filament\Operations\Resources\StaffTaskResource;
use App\Models\Incident;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStaffTasks extends ListRecords
{
    protected static string $resource = StaffTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('reportIssue')
                ->label('Report an Issue')
                ->authorize(fn (): bool => auth()->user()?->isStaff() ?? false)
                ->form([
                    Forms\Components\TextInput::make('title')->required()->maxLength(255),
                    Forms\Components\Select::make('type')->label('Issue type')->options([
                        'maintenance_failure' => 'Repair needed',
                        'property_damage' => 'Damage',
                        'missing_item' => 'Missing item or supplies',
                        'security_concern' => 'Safety or security',
                        'other' => 'Other',
                    ])->required(),
                    Forms\Components\Textarea::make('description')
                        ->label('Location and what happened')
                        ->required()->maxLength(4000),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->isStaff(), 403);

                    Incident::create([
                        'title' => $data['title'],
                        'type' => $data['type'],
                        'description' => $data['description'],
                        'reported_by' => auth()->id(),
                        'severity' => 'medium',
                        'status' => 'reported',
                    ]);

                    Notification::make()->success()->title('Issue reported to Operations')->send();
                }),
        ];
    }
}
