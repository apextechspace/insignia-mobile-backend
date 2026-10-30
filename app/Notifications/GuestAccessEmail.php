<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Deliver immediately: short-lived login codes must not wait for the receipt queue.
class GuestAccessEmail extends Notification
{
    public function __construct(public string $code, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Access your stay | Insignia Courts')
            ->view('emails.guest-access', ['code' => $this->code, 'url' => $this->url]);
    }
}
