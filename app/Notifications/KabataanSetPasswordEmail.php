<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KabataanSetPasswordEmail extends Notification
{
    public function __construct(public string $setPasswordUrl)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Set Your KK Profiling Account Password')
            ->greeting('Hello!')
            ->line('Thank you for submitting your KK Profiling registration.')
            ->line('Click the button below to verify your email and set your account password.')
            ->action('Set Password', $this->setPasswordUrl)
            ->line('This link will expire in 24 hours for your security.')
            ->line('If you did not submit this form, no further action is required.');
    }
}
