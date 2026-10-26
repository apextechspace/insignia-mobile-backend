<?php

namespace App\Filament\Operations\Resources\IncidentResource\Pages;

use App\Filament\Operations\Resources\IncidentResource;
use App\Models\Incident;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIncidents extends ListRecords
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export Incidents CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return response()->streamDownload(function () {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Incident Code', 'Title', 'Type', 'Severity', 'Status', 'Escalated to IPL', 'Associated Cost (NGN)', 'Created At']);
                        Incident::orderBy('created_at', 'desc')->chunk(100, function ($rows) use ($handle) {
                            foreach ($rows as $row) {
                                fputcsv($handle, [
                                    $row->incident_code,
                                    $row->title,
                                    $row->type,
                                    $row->severity,
                                    $row->status,
                                    $row->is_escalated_to_ipl ? 'YES' : 'NO',
                                    $row->associated_cost,
                                    $row->created_at?->format('Y-m-d H:i:s'),
                                ]);
                            }
                        });
                        fclose($handle);
                    }, 'incidents-log-' . date('Y-m-d') . '.csv');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
