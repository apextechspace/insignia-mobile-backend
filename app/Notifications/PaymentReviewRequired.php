<?php

namespace App\Notifications;

use App\Notifications\Channels\PaymentPushChannel;
use Illuminate\Notifications\Notification;

class PaymentReviewRequired extends Notification
{
    public function __construct(private string $reference, private string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database', PaymentPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_review', 'status' => 'needs_review',
            'title' => 'Payment needs finance review',
            'message' => "Payment {$this->reference} requires review: " . str_replace('_', ' ', $this->reason) . '.',
            'reference' => $this->reference, 'reason' => $this->reason,
        ];
    }
}
