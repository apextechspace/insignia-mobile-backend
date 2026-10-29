<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\FoodOrder;
use App\Models\Order;
use App\Notifications\CustomerPaymentEmail;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentEmailService
{
    public function send(Model $record, bool $receipt = true, ?string $reference = null): void
    {
        // Receipt delivery is only entered by verified settlement workflows.
        if (!$record->user || ($receipt && !$record->paid_at)) {
            return;
        }

        $description = match (true) {
            $record instanceof Booking => $record->room?->name ?? 'Suite reservation',
            $record instanceof FoodOrder => 'Dining order',
            $record instanceof Order => $record->product?->name ?? 'Boutique order',
            default => $record->service?->name ?? 'Concierge service',
        };
        $record->user->notify(new CustomerPaymentEmail([
            'title' => $receipt ? 'Payment receipt' : 'Room booking received',
            'receipt' => $receipt,
            'number' => class_basename($record) . '-' . $record->getKey(),
            'description' => $description,
            'amount' => number_format((float) ($record instanceof FoodOrder ? $record->total_amount : $record->total_price), 2),
            'reference' => $reference ?: ($record->flutterwave_reference ?: ($record->payment_reference ?: class_basename($record) . '-' . $record->getKey())),
            'method' => $record->payment_method ? ucwords(str_replace('_', ' ', $record->payment_method)) : 'Awaiting payment',
            'date' => $receipt ? (string) $record->paid_at : (string) $record->created_at,
            'check_in' => $record instanceof Booking ? $record->check_in : null,
            'check_out' => $record instanceof Booking ? $record->check_out : null,
        ]));
    }
}
