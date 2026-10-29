<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Database-backed notification for gateway-verified paid commerce records.
 * Each record belongs to a known source table; no action depends on client claims.
 */
class VerifiedCommercePayment extends Notification
{
    public function __construct(
        private string $orderType,
        private int $orderId,
        private string $title,
        private string $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', \App\Notifications\Channels\PaymentPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'verified_commerce_payment',
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'title' => $this->title,
            'message' => $this->message,
            'status' => 'confirmed',
        ];
    }
}
