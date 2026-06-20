<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TicketAssigned extends Notification
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
            ->subject('Ticket Assigned: ' . $this->ticket->subject)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A ticket has been assigned to you.')
            ->line('Subject: ' . $this->ticket->subject)
            ->line('Priority: ' . ucfirst($this->ticket->priority))
            ->line('Property: ' . $this->ticket->property->name)
            ->action('View Ticket', url('/tickets/' . $this->ticket->id))
            ->line('Please review and address this ticket.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Ticket assigned: ' . $this->ticket->subject,
            'ticket_id' => $this->ticket->id,
            'type' => 'ticket_assigned',
        ];
    }
}
