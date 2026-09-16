<?php

namespace App\Modules\Profile\Notifications;

use App\Support\MailUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeVerificationNotification extends Notification
{
    public function __construct(
        public readonly string $plainToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = MailUrl::route('change-password.confirm', [
            'id' => $notifiable->id,
            'token' => $this->plainToken,
        ]);

        return (new MailMessage)
            ->subject('Confirm Your Kabataan Password Change')
            ->greeting('Hello!')
            ->line('You requested to change the password on your SK OnePortal Kabataan account.')
            ->line('Email: '.$notifiable->email)
            ->action('Confirm Password Change', $url)
            ->line('Your current password stays active until you confirm this link.')
            ->line('After confirming, you will be taken to your home dashboard. Other devices will be signed out.')
            ->line('If you requested a new confirmation email, only the latest link will work.')
            ->line('This link expires in 60 minutes. If you did not request this, you can ignore this email.');
    }
}
