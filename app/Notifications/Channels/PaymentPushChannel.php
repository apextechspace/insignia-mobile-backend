<?php

namespace App\Notifications\Channels;

use App\Jobs\DeliverPaymentPush;
use Illuminate\Notifications\Notification;

class PaymentPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!$notifiable->expo_push_token) {
            return;
        }

        $data = $notification->toArray($notifiable);
        DeliverPaymentPush::dispatch($notifiable->getKey(), $data['title'], $data['message'], $data)
            ->onConnection('database')->beforeCommit();
    }
}
