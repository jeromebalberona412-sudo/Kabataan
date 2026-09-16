<?php

namespace App\Support;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

/**
 * Build public URLs for email links and same-origin paths for the browser.
 * Prefers APP_PUBLIC_URL, then a non-localhost APP_URL, then the current request host
 * so production never ships localhost links when the live domain is already serving the page.
 */
class MailUrl
{
    public static function root(): string
    {
        $candidates = [
            config('services.kabataan_app_url'),
            config('app.public_url'),
            config('app.url'),
        ];

        try {
            $requestRoot = request()->getSchemeAndHttpHost();
            if ($requestRoot !== '' && ! self::isLoopback($requestRoot) && ! self::isPrivateLan($requestRoot)) {
                $candidates[] = $requestRoot;
            }
        } catch (\Throwable) {
            // ignore
        }

        foreach ($candidates as $candidate) {
            $normalized = self::normalize($candidate);
            if ($normalized !== null && ! self::isLoopback($normalized) && ! self::isPrivateLan($normalized)) {
                return rtrim($normalized, '/');
            }
        }

        if (app()->environment('local', 'testing')) {
            foreach ($candidates as $candidate) {
                $normalized = self::normalize($candidate);
                if ($normalized !== null) {
                    return rtrim($normalized, '/');
                }
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = self::normalize($candidate);
            if ($normalized !== null && ! self::isLoopback($normalized)) {
                return rtrim($normalized, '/');
            }
        }

        try {
            $requestRoot = self::normalize(request()->getSchemeAndHttpHost());
            if ($requestRoot !== null && ! self::isLoopback($requestRoot)) {
                return rtrim($requestRoot, '/');
            }
        } catch (\Throwable) {
            // ignore
        }

        $fallback = self::normalize(config('app.url'));

        return $fallback !== null ? rtrim($fallback, '/') : '';
    }

    public static function to(string $path): string
    {
        $root = rtrim(self::root(), '/');
        $path = ltrim($path, '/');

        if ($root === '') {
            return '/'.$path;
        }

        return $root.'/'.$path;
    }

    /**
     * Same-origin path for JS fetch/navigation. Never includes http://localhost.
     */
    public static function uri(string $path): string
    {
        return self::sameOrigin(self::to($path));
    }

    public static function sameOrigin(string $urlOrPath): string
    {
        $value = trim($urlOrPath);
        if ($value === '') {
            return '/';
        }

        if (! preg_match('#^https?://#i', $value)) {
            return '/'.ltrim($value, '/');
        }

        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $query = parse_url($value, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? $path.'?'.$query : $path;
    }

    /**
     * Public file URL for the browser. Loopback hosts become same-origin paths;
     * Cloudinary and other remote URLs stay absolute.
     */
    public static function media(string $urlOrPath): string
    {
        $value = trim($urlOrPath);
        if ($value === '') {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return self::isLoopback($value) ? self::sameOrigin($value) : $value;
        }

        return self::uri('/'.ltrim($value, '/'));
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

    public static function isLoopback(?string $url): bool
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

        return $host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '[::1]'
            || str_ends_with($host, '.localhost');
    }

    public static function isPrivateLan(?string $url): bool
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

        if ($host === '' || str_ends_with($host, '.local')) {
            return $host !== '';
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        return false;
    }

    public static function normalize(mixed $url): ?string
    {
        $value = trim((string) $url);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        return $scheme.'://'.$host.$port.$path;
    }

    private static function withPublicRoot(callable $callback): string
    {
        $appRoot = rtrim((string) config('app.url'), '/');
        $publicRoot = self::root();

        if ($publicRoot === '' || ! filter_var($publicRoot, FILTER_VALIDATE_URL)) {
            return (string) $callback();
        }

        URL::forceRootUrl($publicRoot);
        self::applySchemeFromUrl($publicRoot);

        try {
            return (string) $callback();
        } finally {
            $restore = filter_var($appRoot, FILTER_VALIDATE_URL) ? $appRoot : $publicRoot;
            URL::forceRootUrl($restore);
            self::applySchemeFromUrl($restore);
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
