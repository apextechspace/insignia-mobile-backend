<?php

namespace App\Filament\Pages;

use App\Services\IplFinancialStatement;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IplMasterStatement extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Master Financial Statement';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.ipl-master-statement';

    public string $fromDate = '';
    public string $toDate = '';
    public string $sourceFilter = 'all';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isIplAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        return ['statement' => $this->statement()];
    }

    public function statement(): array
    {
        abort_unless(static::canAccess(), 403);
        return app(IplFinancialStatement::class)->report(
            $this->fromDate, $this->toDate, $this->sourceFilter
        );
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $statement = $this->statement();
        $filename = "ipl-operating-statement-{$statement['from']}-{$statement['to']}.csv";

        return response()->streamDownload(function () use ($statement): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Source', 'Type', 'Reference', 'Method', 'Description', 'Amount NGN', 'Period Running NGN']);
            foreach ($statement['rows'] as $row) {
                // Prefix potentially executable spreadsheet cells to prevent
                // CSV formula injection from user-supplied descriptions/refs.
                $safe = fn (string $value): string =>
                    preg_match('/^[\s]*[=+\-@\t\r]/u', $value) ? "'" . $value : $value;
                fputcsv($out, [
                    $row['date'], $row['source'], $row['type'], $safe($row['reference']),
                    $safe($row['method']), $safe($row['description']),
                    number_format($row['amount_cents'] / 100, 2, '.', ''),
                    number_format($row['running_cents'] / 100, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
