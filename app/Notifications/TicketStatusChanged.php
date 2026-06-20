<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TicketStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ticket Update: ' . $this->ticket->subject)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your ticket status has been updated.')
            ->line('Subject: ' . $this->ticket->subject)
            ->line('New Status: ' . ucfirst(str_replace('_', ' ', $this->ticket->status)))
            ->action('View Ticket', url('/tickets/' . $this->ticket->id))
            ->line('Thank you for your patience.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Ticket status updated to ' . str_replace('_', ' ', $this->ticket->status) . ': ' . $this->ticket->subject,
            'ticket_id' => $this->ticket->id,
            'type' => 'ticket_status_changed',
        ];
    }
}
