<?php

namespace App\Filament\Operations\Resources\MonthlyStatementResource\Pages;

use App\Filament\Operations\Resources\MonthlyStatementResource;
use App\Models\MonthlyStatement;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyStatements extends ListRecords
{
    protected static string $resource = MonthlyStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export Statements CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Statement Code', 'Year', 'Month', 'Revenue (NGN)', 'Expenses (NGN)', 'Mgmt Fee (NGN)', 'Net to IPL (NGN)', 'Status']);
                        MonthlyStatement::orderBy('year', 'desc')->orderBy('month', 'desc')->chunk(50, function ($rows) use ($handle) {
                            foreach ($rows as $row) {
                                $monthName = is_numeric($row->month) && (int)$row->month >= 1 && (int)$row->month <= 12 
                                    ? date('F', mktime(0, 0, 0, (int)$row->month, 1)) 
                                    : $row->month;
                                fputcsv($handle, [
                                    $row->statement_code,
                                    $row->year,
                                    $monthName,
                                    $row->total_accommodation_revenue,
                                    $row->total_permitted_expenses,
                                    $row->management_fee_amount,
                                    $row->net_payable_to_ipl,
                                    $row->status,
                                ]);
                            }
                        });
                        fclose($handle);
                    }, 'monthly-statements-' . date('Y-m-d') . '.csv');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
