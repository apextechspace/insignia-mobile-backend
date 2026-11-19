<?php

namespace App\Http\Controllers;

use App\Services\IplFinancialStatement;
use Illuminate\Http\Request;

class IplStatementPrintController extends Controller
{
    public function __invoke(Request $request, IplFinancialStatement $reports)
    {
        abort_unless($request->user()?->isIplAdmin(), 403);
        $filters = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d',
            'source' => 'required|in:'.implode(',', IplFinancialStatement::SOURCES),
        ]);
        $statement = $reports->report($filters['from'], $filters['to'], $filters['source']);
        // A standalone print response must not inherit panel asset state.
        \Livewire\Livewire::flushState();

        return response()->view('statements.ipl-print', [
            'statement' => $statement,
            'generatedAt' => now(config('property.timezone')),
        ])->header('Cache-Control', 'private, no-store');
    }
}
