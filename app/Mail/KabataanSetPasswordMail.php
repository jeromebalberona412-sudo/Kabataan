<?php

namespace App\Mail;

use App\Support\MailUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class KabataanSetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $setPasswordUrl)
    {
    }

    public function build(): self
    {
        // Prefer PNG for email clients (Gmail/Outlook); webp is less reliable in mail.
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

        // Absolute public URL fallback when CID embed is blocked.
        $logoUrl = $logoPath
            ? MailUrl::root().'/images/'.rawurlencode(basename($logoPath))
            : MailUrl::root().'/images/SK_OnePortal_logo.png';

        return $this->subject('Set Your KK Profiling Account Password')
            ->view('emails.kkprofiling-set-password', [
                'setPasswordUrl' => $this->setPasswordUrl,
                'logoPath' => $logoPath,
                'logoUrl' => $logoUrl,
            ]);
    }
}
