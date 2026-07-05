<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingStatusUpdated extends Notification
{
    use Queueable;

    protected $booking;
    protected $status;

    /**
     * Create a new notification instance.
     */
    public function __construct($booking, $status)
    {
        $this->booking = $booking;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $serviceName = $this->booking->service->name ?? 'Service';
        
        $messages = [
            'confirmed' => "Your booking for {$serviceName} has been confirmed. Our team is preparing for your session.",
            'in-progress' => "Our concierge is now attending to your {$serviceName} request.",
            'completed' => "Your {$serviceName} service has been successfully completed. We hope you enjoyed it!",
            'cancelled' => "We regret to inform you that your {$serviceName} booking has been cancelled. Please contact concierge for details.",
            'pending' => "Your {$serviceName} request has been received and is awaiting review.",
        ];

        return [
            'booking_id' => $this->booking->id,
            'title' => "Booking " . ucfirst($this->status),
            'message' => $messages[$this->status] ?? "Your booking status has been updated to {$this->status}.",
            'status' => $this->status,
            'icon' => $this->getIcon($this->status),
        ];
    }

    protected function getIcon($status)
    {
        return match($status) {
            'confirmed' => 'checkmark-circle',
            'in-progress' => 'time',
            'completed' => 'star',
            'cancelled' => 'close-circle',
            default => 'notifications',
        };
    }
}
