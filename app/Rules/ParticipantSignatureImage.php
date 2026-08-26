<?php

namespace App\Rules;

use App\Services\ParticipantSignatureValidationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ParticipantSignatureImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = app(ParticipantSignatureValidationService::class)->validate(
            is_string($value) ? $value : null
        );

        if (! $result['ok']) {
            $fail($result['error'] ?? config('signature.messages.invalid'));
        }
    }
}
