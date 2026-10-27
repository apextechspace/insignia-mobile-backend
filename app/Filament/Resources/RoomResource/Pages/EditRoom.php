<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    public function getTitle(): string | Htmlable
    {
        return "Edit: {$this->record->name}";
    }

    public function getHeading(): string | Htmlable
    {
        return "Edit {$this->record->name}";
    }

    public function getSubheading(): string | Htmlable | null
    {
        return "Manage suite identity, commercial pricing, housekeeping readiness, amenities, and photo gallery.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Preview Suite')
                ->icon('heroicon-m-eye')
                ->color('gray')
                ->slideOver(),
            Actions\DeleteAction::make()
                ->icon('heroicon-m-trash'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save Changes')
                ->icon('heroicon-m-check'),
            $this->getCancelFormAction()
                ->label('Cancel')
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Suite details updated successfully.';
    }
}
