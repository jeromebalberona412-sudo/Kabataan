<?php

namespace App\Modules\Profile\Notifications;

use App\Models\User;
use App\Support\MailUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeVerificationNotification extends Notification
{
    public function __construct(
        private readonly User $user,
        private readonly string $plainToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = MailUrl::route('change-email.confirm', [
            'id' => $this->user->id,
            'token' => $this->plainToken,
        ]);

        return (new MailMessage)
            ->subject('Confirm Your New Kabataan Email')
            ->greeting('Hello!')
            ->line('You requested to change the email address on your SK OnePortal Kabataan account.')
            ->line('Current email: '.$this->user->email)
            ->line('New email: '.$this->user->pending_email)
            ->action('Confirm Email Change', $url)
            ->line('Your current email stays active until you confirm this link.')
            ->line('This email link only shows a confirmation page and will not sign you into the dashboard.')
            ->line('If Kabataan is still open on the verify page, that page will take you to the dashboard after you confirm.')
            ->line('If you requested a new confirmation email, only the latest link will work.')
            ->line('This link expires in 60 minutes. If you did not request this, you can ignore this email.');
    }
}
