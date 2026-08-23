<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FacebookProfileUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = trim((string) $value);

        if ($url === '') {
            return;
        }

        if (strlen($url) < 3) {
            $fail('Facebook profile link must be at least 3 characters.');

            return;
        }

        if (strlen($url) > 50) {
            $fail('Facebook profile link must not exceed 50 characters.');

            return;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $fail('Please enter a valid Facebook profile link.');

            return;
        }

        if (! preg_match('/^https?:\/\/(www\.|m\.)?(facebook\.com|fb\.com)\//i', $url)) {
            $fail('Link must be a Facebook profile URL (e.g. https://www.facebook.com/yourprofile).');
        }
    }
}
