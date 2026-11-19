<?php

namespace App\Http\Controllers;

use App\Filament\Operations\Pages\AccommodationStatement;
use App\Services\IplFinancialStatement;
use Illuminate\Http\Request;

class AccommodationStatementPrintController extends Controller
{
    public function __invoke(Request $request, IplFinancialStatement $reports)
    {
        abort_unless(AccommodationStatement::canAccess(), 403);
        $filters = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d']);
        \Livewire\Livewire::flushState();
        return response()->view('statements.ipl-print', [
            'statement' => $reports->report($filters['from'], $filters['to'], 'accommodation'),
            'generatedAt' => now(config('property.timezone')),
            'reportTitle' => 'Accommodation Receipts Statement', 'reportAudience' => 'VRL accommodation report',
            'outflowLabel' => 'Recorded accommodation refunds',
        ])->header('Cache-Control', 'private, no-store');
    }
}
