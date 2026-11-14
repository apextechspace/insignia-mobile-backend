<?php

namespace App\Filament\Operations\Resources\MonthlyStatementResource\Pages;

use App\Filament\Operations\Resources\MonthlyStatementResource;
use App\Services\MclStatementReport;
use Filament\Resources\Pages\ViewRecord;

class ViewMonthlyStatement extends ViewRecord
{
    protected static string $resource = MonthlyStatementResource::class;

    protected static string $view = 'filament.operations.monthly-statement';

    protected function getViewData(): array
    {
        $statement = $this->getRecord();
        $draft = in_array($statement->status, ['draft', 'queried'], true);

        return [
            'statement' => $statement,
            'draft' => $draft,
            'report' => $draft ? app(MclStatementReport::class)->build($statement) : $statement->snapshot,
        ];
    }
}
