<?php

namespace App\Filament\Pages;

use App\Services\AccommodationReconciliation;
use Filament\Pages\Page;

class AccommodationReconciliationReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationLabel = 'Accommodation Reconciliation';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.accommodation-reconciliation';

    public static function canAccess(): bool
    {
        return auth()->user()?->isIplAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        return [
            'exceptions' => app(AccommodationReconciliation::class)->exceptions(),
        ];
    }
}
