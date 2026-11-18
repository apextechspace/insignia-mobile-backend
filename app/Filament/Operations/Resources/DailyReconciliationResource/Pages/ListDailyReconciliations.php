<?php

namespace App\Filament\Operations\Resources\DailyReconciliationResource\Pages;

use App\Filament\Operations\Resources\DailyReconciliationResource;
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
                        $owner = auth()->user()?->isIplAdmin();
                        $headers = ['Date', 'Shift', 'Recorded Accomm (NGN)', 'Actual POS (NGN)', 'Actual Transfer (NGN)', 'Cash (NGN)', 'Discrepancy (NGN)', 'Status'];
                        if ($owner) {
                            array_splice($headers, 3, 0, ['Recorded Ancillary (NGN)']);
                        }
                        fputcsv($handle, $headers);
                        DailyReconciliationResource::getEloquentQuery()->orderBy('reconciliation_date', 'desc')->chunk(100, function ($rows) use ($handle, $owner) {
                            foreach ($rows as $row) {
                                $values = [
                                    $row->reconciliation_date?->format('Y-m-d'),
                                    $row->shift,
                                    $row->recorded_accommodation,
                                    $row->actual_pos_total,
                                    $row->actual_transfer_total,
                                    $row->actual_cash_counted,
                                    $row->discrepancy,
                                    $row->status,
                                ];
                                if ($owner) {
                                    array_splice($values, 3, 0, [$row->recorded_ancillary_cash]);
                                }
                                fputcsv($handle, $values);
                            }
                        });
                        fclose($handle);
                    }, 'daily-reconciliations-'.date('Y-m-d').'.csv');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
