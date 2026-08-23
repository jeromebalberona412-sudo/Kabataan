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
        $public = trim((string) config('app.public_url'));

        if ($public !== '' && filter_var($public, FILTER_VALIDATE_URL)) {
            return rtrim($public, '/');
        }

        return rtrim((string) config('app.url'), '/');
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
