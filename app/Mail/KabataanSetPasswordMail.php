<?php

namespace App\Mail;

use App\Support\MailUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class KabataanSetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $setPasswordUrl) {}

    public function build(): self
    {
        // Use absolute public URL only — avoid CID embed() which can 500
        // when build()/preview runs outside a live Swift/Symfony message context.
        $root = rtrim((string) MailUrl::root(), '/');
        if ($root === '') {
            $root = rtrim((string) request()->getSchemeAndHttpHost(), '/');
        }

        $logoFile = 'SK_OnePortal_logo.png';
        foreach (['SK_OnePortal_logo.png', 'SK_OnePortal.png', 'skoneportal_logo.png'] as $candidate) {
            $path = public_path('images/'.$candidate);
            if (is_string($path) && is_file($path)) {
                $logoFile = $candidate;
                break;
            }
        }

        $logoUrl = $root.'/images/'.rawurlencode($logoFile);

        $url = trim((string) $this->setPasswordUrl);
        if ($url === '') {
            $url = $root.'/';
        }

        try {
            return $this->subject('Set Your KK Profiling Account Password')
                ->view('emails.kkprofiling-set-password', [
                    'setPasswordUrl' => $url,
                    'logoPath' => null,
                    'logoUrl' => $logoUrl,
                ]);
        } catch (Throwable) {
            return $this->subject('Set Your KK Profiling Account Password')
                ->view('emails.kkprofiling-set-password', [
                    'setPasswordUrl' => $url,
                    'logoPath' => null,
                    'logoUrl' => $logoUrl,
                ]);
        }
    }
}
