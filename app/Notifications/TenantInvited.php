<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TenantInvited extends Notification
{
    use Queueable;

    public function __construct(
        public User $tenant,
        public string $password,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Turtle - Your Account Has Been Created')
            ->greeting('Hello ' . $this->tenant->name . ',')
            ->line('Your account has been created on the Turtle Tenant Management Portal.')
            ->line('Your temporary password is: **' . $this->password . '**')
            ->line('Please log in and change your password immediately.')
            ->action('Log In', url('/login'))
            ->line('If you did not expect this invitation, please ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Welcome to Turtle! Your account has been created.',
            'type' => 'tenant_invited',
        ];
    }
}
