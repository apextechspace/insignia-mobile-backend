<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\MonthlyStatement;
use App\Models\PaymentReconciliationCase;
use Filament\Widgets\Widget;

class OwnerCommandWidget extends Widget
{
    protected static string $view = 'filament.widgets.owner-command';
    protected static ?int $sort = -10;
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        return [
            'pendingReceipts' => Booking::where('status', 'pending')
                ->whereNull('paid_at')
                ->where('payment_method', 'bank_transfer')
                ->whereNotNull('receipt_url')->count(),
            'paymentExceptions' => PaymentReconciliationCase::where('status', 'needs_review')->count(),
            'statementsAwaitingReview' => MonthlyStatement::where('status', 'submitted_to_ipl')->count(),
        ];
    }
}
