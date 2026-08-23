<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KabataanRegistrationApprovedEmail extends Notification
{
    public function __construct(
        public string $fullName,
        public string $barangayName,
        public string $loginUrl,
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = trim($this->fullName) !== '' ? $this->fullName : 'Kabataan';

        return (new MailMessage)
            ->subject('KK Profiling Approved — SK OnePortal')
            ->greeting("Hello, {$name}!")
            ->line('Your KK Profiling registration has been approved.')
            ->line('Your submitted details matched a previous KK profiling record for '.$this->barangayName.', so your account is now active.')
            ->line('You may log in to SK OnePortal to access programs, events, and other SK services.')
            ->action('Log In to SK OnePortal', $this->loginUrl)
            ->line('Thank you for registering with your Sangguniang Kabataan.');
    }
}
