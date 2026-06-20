<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LeaseUploaded extends Notification
{
    use Queueable;

    public function __construct(
        public Lease $lease,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Lease Document: ' . $this->lease->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new lease document has been uploaded for your property.')
            ->line('Title: ' . $this->lease->title)
            ->line('Property: ' . $this->lease->property->name)
            ->action('View Lease', url('/leases/' . $this->lease->id))
            ->line('Please review the document at your convenience.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'New lease uploaded: ' . $this->lease->title,
            'lease_id' => $this->lease->id,
            'type' => 'lease_uploaded',
        ];
    }
}
