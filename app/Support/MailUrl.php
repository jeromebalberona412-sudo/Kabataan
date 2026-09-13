<?php

namespace App\Support;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

/**
 * Build absolute URLs for outbound email links.
 * Uses APP_PUBLIC_URL when set so links work from phones and outside the dev LAN.
 */
class MailUrl
{
    public static function root(): string
    {
        $preferred = null;

        foreach ([config('app.public_url'), config('app.url')] as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '' || ! filter_var($value, FILTER_VALIDATE_URL)) {
                continue;
            }

            $host = strtolower((string) parse_url($value, PHP_URL_HOST));
            $isLoopback = $host === ''
                || $host === 'localhost'
                || $host === '127.0.0.1'
                || $host === '[::1]'
                || str_ends_with($host, '.localhost');

            if (! $isLoopback) {
                return rtrim($value, '/');
            }

            $preferred ??= rtrim($value, '/');
        }

        if (app()->environment('local', 'testing') && $preferred !== null) {
            return $preferred;
        }

        $fallback = $preferred ?? rtrim((string) config('app.url'), '/');

        // Last resort: never return empty — broken absolute links cause production mail 500s.
        if ($fallback === '' || ! filter_var($fallback, FILTER_VALIDATE_URL)) {
            try {
                $requestRoot = rtrim((string) request()->root(), '/');
                if ($requestRoot !== '' && filter_var($requestRoot, FILTER_VALIDATE_URL)) {
                    return $requestRoot;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return $fallback !== '' ? $fallback : 'http://localhost';
    }

    public static function route(string $name, array $parameters = []): string
    {
        return self::withPublicRoot(
            fn (): string => route($name, $parameters, absolute: true)
        );
    }

    public static function temporarySignedRoute(
        string $name,
        DateTimeInterface|DateInterval|int $expiration,
        array $parameters = [],
    ): string {
        return self::withPublicRoot(
            fn (): string => URL::temporarySignedRoute($name, $expiration, $parameters, absolute: true)
        );
    }

    private static function withPublicRoot(callable $callback): string
    {
        $appRoot = rtrim((string) config('app.url'), '/');
        $publicRoot = self::root();

        URL::forceRootUrl($publicRoot);
        self::applySchemeFromUrl($publicRoot);

        try {
            return (string) $callback();
        } finally {
            URL::forceRootUrl($appRoot);
            self::applySchemeFromUrl($appRoot);
        }
    }

    private static function applySchemeFromUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme === 'https') {
            URL::forceScheme('https');
        } elseif ($scheme === 'http') {
            URL::forceScheme('http');
        }
    }
}
