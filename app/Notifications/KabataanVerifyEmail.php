<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KabataanVerifyEmail extends Notification
{
    public function __construct(public string $verificationUrl)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your KK Profiling Registration')
            ->greeting('Hello!')
            ->line('Thank you for submitting your KK Profiling form.')
            ->line('Please verify your email address to continue with your registration.')
            ->action('Verify Email Address', $this->verificationUrl)
            ->line('This verification link will expire in 24 hours for your security.')
            ->line('If you did not submit this form, no further action is required.');
    }
}
