<?php

namespace App\Filament\Operations\Resources\ExpenseResource\Pages;

use App\Filament\Operations\Resources\ExpenseResource;
use App\Models\Expense;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export Expenses CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['ID', 'Title', 'Category', 'Amount (NGN)', 'Payment Method', 'Status', 'Date', 'Description']);
                        Expense::orderBy('expense_date', 'desc')->chunk(100, function ($rows) use ($handle) {
                            foreach ($rows as $row) {
                                fputcsv($handle, [
                                    $row->id,
                                    $row->title,
                                    $row->category,
                                    $row->amount,
                                    $row->payment_method,
                                    $row->status,
                                    $row->expense_date?->format('Y-m-d'),
                                    $row->description,
                                ]);
                            }
                        });
                        fclose($handle);
                    }, 'expenses-audit-' . date('Y-m-d') . '.csv');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
