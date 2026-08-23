<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KabataanProfilingEmailChangeVerify extends Notification
{
    public function __construct(public string $verificationUrl, public int $profilingYear)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your New Email for KK Profiling Update')
            ->greeting('Hello!')
            ->line('You requested to change your email while updating your KK Profiling for '.$this->profilingYear.'.')
            ->line('Please verify this email address to complete your KK Profiling update.')
            ->action('Verify Email Address', $this->verificationUrl)
            ->line('This verification link will expire in 24 hours.')
            ->line('If you did not request this change, no further action is required.');
    }
}
