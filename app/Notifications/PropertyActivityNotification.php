<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PropertyActivityNotification extends Notification
{
    public function __construct(private string $title, private string $message, private string $kind, private int $recordId) {}

    public function via(object $notifiable): array
    {
        return ['database', Channels\PaymentPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => $this->kind, 'record_id' => $this->recordId, 'title' => $this->title, 'message' => $this->message];
    }

    public function toDatabase(object $notifiable): array
    {
        return \App\Support\PortalNotificationData::format($this->toArray($notifiable));
    }
}
