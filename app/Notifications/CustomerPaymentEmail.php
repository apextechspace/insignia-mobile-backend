<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerPaymentEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(public array $details)
    {
        // Persist the mail job in the same database transaction as settlement.
        $this->onConnection('database')->beforeCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->details['title'] . ' | Insignia Courts')
            ->view('emails.customer-payment', [
                'details' => $this->details,
                'customerName' => $notifiable->name,
            ]);
    }
}
