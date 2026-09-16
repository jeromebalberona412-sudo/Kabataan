<?php

namespace App\Notifications;

use App\Mail\KabataanSetPasswordMail;
use App\Support\MailUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KabataanSetPasswordEmail extends Notification
{
    public function __construct(public string $setPasswordUrl) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Prefer a Mailable with an explicit To address.
     * Laravel does not auto-fill To when toMail() returns a Mailable.
     */
    public function toMail($notifiable): KabataanSetPasswordMail|MailMessage
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        if (is_string($address) && $address !== '') {
            return (new KabataanSetPasswordMail($this->setPasswordUrl))->to($address);
        }

        if (is_array($address)) {
            $mailable = new KabataanSetPasswordMail($this->setPasswordUrl);
            foreach ($address as $email => $name) {
                if (is_int($email)) {
                    $mailable->to($name);
                } else {
                    $mailable->to($email, $name);
                }
            }

            return $mailable;
        }

        // Fallback: plain MailMessage with embeddable PNG when possible.
        $logoPath = null;
        foreach ([
            public_path('images/SK_OnePortal_logo.png'),
            public_path('images/SK_OnePortal.png'),
            public_path('images/skoneportal_logo.webp'),
        ] as $path) {
            if (is_string($path) && is_file($path)) {
                $logoPath = $path;
                break;
            }
        }

        return (new MailMessage)
            ->subject('Set Your KK Profiling Account Password')
            ->view('emails.kkprofiling-set-password', [
                'setPasswordUrl' => $this->setPasswordUrl,
                'logoPath' => $logoPath,
                'logoUrl' => MailUrl::to('images/'.($logoPath ? rawurlencode(basename($logoPath)) : 'SK_OnePortal_logo.png')),
            ]);
    }
}
