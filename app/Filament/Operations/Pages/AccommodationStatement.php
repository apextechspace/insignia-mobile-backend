<?php

namespace App\Filament\Operations\Pages;

use App\Filament\Pages\IplMasterStatement;
use App\Services\IplFinancialStatement;

class AccommodationStatement extends IplMasterStatement
{
    protected static ?string $navigationLabel = 'Accommodation Receipts';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.operations.pages.accommodation-statement';

    public function getTitle(): string { return 'Accommodation Receipts Statement'; }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user !== null && ($user->isIplAdmin() || $user->isDeliveryPartner());
    }

    public function statement(): array
    {
        abort_unless(static::canAccess(), 403);
        // Never accept a client-supplied department for the operations report.
        return app(IplFinancialStatement::class)->report($this->fromDate, $this->toDate, 'accommodation');
    }

    public function setPeriod(string $period): void
    {
        abort_unless(static::canAccess(), 403);
        $today = today(config('property.timezone'));
        [$from, $to] = match ($period) {
            'this_month' => [$today->copy()->startOfMonth(), $today],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->copy()->startOfYear(), $today],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            default => throw \Illuminate\Validation\ValidationException::withMessages(['period' => 'Choose a supported reporting period.']),
        };
        $this->fromDate = $from->toDateString();
        $this->toDate = $to->toDateString();
    }

    protected function exportFilename(array $statement): string
    {
        return "accommodation-receipts-{$statement['from']}-{$statement['to']}.csv";
    }
}
