<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KabataanProfilingUpdatedEmail extends Notification
{
    public function __construct(
        public string $fullName,
        public int $profilingYear,
        public string $barangayName,
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
            ->subject('KK Profiling Updated — SK OnePortal')
            ->greeting("Hello, {$name}!")
            ->line('Your KK Profiling information for '.$this->profilingYear.' has been successfully updated.')
            ->line('Your updated profile for '.$this->barangayName.' is now on file with your Sangguniang Kabataan.')
            ->line('You may continue using SK OnePortal for programs, events, and other SK services.')
            ->line('Thank you for keeping your KK Profiling information up to date.');
    }
}
