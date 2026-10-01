<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Propaganistas\LaravelDisposableEmail\DisposableDomains;

/**
 * RFC format + DNS domain checks via Laravel (egulias/email-validator),
 * plus disposable/temporary domain blocking via propaganistas/laravel-disposable-email.
 *
 * KK Profiling practical limits (not RFC SMTP 254):
 * - Local-part (before @): min 6, max 30 (aligned with major providers such as Gmail)
 * - Complete email: max 64 (practical deliverability for PH mail systems)
 *
 * Separate from InvalidEmailService (bounce / delivery-failure cooldowns).
 */
class ValidEmailAddress implements ValidationRule
{
    public const MSG_FORMAT = 'Please enter a valid email address.';

    public const MSG_DOMAIN = 'Please enter a valid email address.';

    public const MSG_LOCAL_MIN = 'Email username must be at least 6 characters.';

    public const MSG_LOCAL_MAX = 'Email username must not exceed 30 characters.';

    public const MSG_MAX = 'Email must not exceed 64 characters.';

    public const MSG_REQUIRED = 'Email is required.';

    public const MSG_DISPOSABLE = 'Temporary or disposable email addresses are not allowed. Please use a permanent email address.';

    /** Minimum characters before @. */
    public const LOCAL_MIN_LENGTH = 6;

    /** Maximum characters before @ (major provider / Gmail-style cap). */
    public const LOCAL_MAX_LENGTH = 30;

    /**
     * Maximum length of the complete email address.
     * Practical PH deliverability cap (not the RFC 254 technical maximum).
     */
    public const MAX_LENGTH = 64;

    /**
     * @return list<string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    public static function profilingRules(): array
    {
        return [
            'required',
            'string',
            'bail',
            'max:'.self::MAX_LENGTH,
            new self,
        ];
    }

    /**
     * @return list<string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    public static function optionalRules(): array
    {
        return [
            'nullable',
            'string',
            'bail',
            'max:'.self::MAX_LENGTH,
            new self,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function profilingMessages(): array
    {
        return [
            'email.required' => self::MSG_REQUIRED,
            'email.max' => self::MSG_MAX,
            'email.indisposable' => self::MSG_DISPOSABLE,
            'new_email.indisposable' => self::MSG_DISPOSABLE,
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(self::MSG_FORMAT);

            return;
        }

        $email = strtolower(trim($value));
        if ($email === '') {
            return;
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            $fail(self::MSG_FORMAT);

            return;
        }

        $localPart = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);
        $localLen = strlen($localPart);

        if ($localLen < self::LOCAL_MIN_LENGTH) {
            $fail(self::MSG_LOCAL_MIN);

            return;
        }

        if ($localLen > self::LOCAL_MAX_LENGTH) {
            $fail(self::MSG_LOCAL_MAX);

            return;
        }

        $rfc = Validator::make(
            ['email' => $email],
            ['email' => ['email:rfc']]
        );

        if ($rfc->fails()) {
            $fail(self::MSG_FORMAT);

            return;
        }

        if ($domain === '' || ! str_contains($domain, '.')) {
            $fail(self::MSG_FORMAT);

            return;
        }

        // Disposable / temporary domains are rejected before the DNS check.
        // The list lookup is local and deterministic, while email:dns is a slow
        // network call that would otherwise report many throwaway providers as a
        // generic "invalid email" before we ever reach the disposable check.
        if ($this->isDisposableAddress($email)) {
            $fail(self::MSG_DISPOSABLE);

            return;
        }

        $dns = Validator::make(
            ['email' => $email],
            ['email' => ['email:dns']]
        );

        if ($dns->fails()) {
            $fail(self::MSG_DOMAIN);
        }
    }

    /**
     * Delegates to propaganistas/laravel-disposable-email, which owns the domain
     * list (`php artisan disposable:update`).
     *
     * Matching covers the exact domain, its subdomains (include_subdomains), and
     * the resolved MX target hosts, so throwaway services that hide behind a
     * subdomain or a mail exchanger are still caught.
     */
    private function isDisposableAddress(string $email): bool
    {
        try {
            return app(DisposableDomains::class)->isDisposable($email, true);
        } catch (\Throwable) {
            // Never let a list/storage problem let disposable addresses through
            // silently: fall back to the validator rule, which is the same
            // package resolved a different way.
            return Validator::make(
                ['email' => $email],
                ['email' => ['indisposable']]
            )->fails();
        }
    }
}
