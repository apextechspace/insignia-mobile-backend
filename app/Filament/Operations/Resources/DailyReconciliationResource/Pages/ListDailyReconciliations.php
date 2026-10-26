<?php

namespace App\Filament\Operations\Resources\DailyReconciliationResource\Pages;

use App\Filament\Operations\Resources\DailyReconciliationResource;
use App\Models\DailyReconciliation;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailyReconciliations extends ListRecords
{
    protected static string $resource = DailyReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export Reconciliations CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Date', 'Shift', 'Recorded Accomm (NGN)', 'Recorded Ancillary (NGN)', 'Actual POS (NGN)', 'Actual Transfer (NGN)', 'Cash (NGN)', 'Discrepancy (NGN)', 'Status']);
                        DailyReconciliation::orderBy('reconciliation_date', 'desc')->chunk(100, function ($rows) use ($handle) {
                            foreach ($rows as $row) {
                                fputcsv($handle, [
                                    $row->reconciliation_date?->format('Y-m-d'),
                                    $row->shift,
                                    $row->recorded_accommodation,
                                    $row->recorded_ancillary_cash,
                                    $row->actual_pos_total,
                                    $row->actual_transfer_total,
                                    $row->actual_cash_counted,
                                    $row->discrepancy,
                                    $row->status,
                                ]);
                            }
                        });
                        fclose($handle);
                    }, 'daily-reconciliations-' . date('Y-m-d') . '.csv');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
