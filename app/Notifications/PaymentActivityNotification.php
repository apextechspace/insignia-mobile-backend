<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PaymentActivityNotification extends Notification
{
    public function __construct(
        private string $title,
        private string $message,
        private int $bookingId,
        private string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'booking_payment',
            'booking_id' => $this->bookingId,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->status,
        ];
    }
}
