<?php

namespace App\Filament\Operations\Resources\AccommodationLedgerResource\Pages;

use App\Filament\Operations\Resources\AccommodationLedgerResource;
use App\Models\AccommodationLedger;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccommodationLedgers extends ListRecords
{
    protected static string $resource = AccommodationLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Reference', 'Room', 'Type', 'Gross (NGN)', 'Net (NGN)', 'Payment Method', 'Channel', 'Status', 'Date']);
                        AccommodationLedger::with('room')->orderBy('transaction_date', 'desc')->chunk(100, function ($rows) use ($handle) {
                            foreach ($rows as $row) {
                                fputcsv($handle, [
                                    $row->reference,
                                    $row->room?->name ?? 'N/A',
                                    $row->type,
                                    $row->gross_amount,
                                    $row->net_amount,
                                    $row->payment_method,
                                    $row->channel,
                                    $row->status,
                                    $row->transaction_date?->format('Y-m-d H:i:s'),
                                ]);
                            }
                        });
                        fclose($handle);
                    }, 'accommodation-ledgers-' . date('Y-m-d') . '.csv');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
