<?php

namespace App\Services;

class DocumentFingerprintService
{
    /**
     * Build a privacy-preserving HMAC fingerprint from normalized ID signals.
     * Never expose the result to browsers, URLs, or client API payloads.
     *
     * @param  array<string, mixed>  $signals
     */
    public function fingerprint(array $signals): ?string
    {
        $normalized = $this->normalize($signals);

        if ($normalized === '') {
            return null;
        }

        $secret = (string) config('documents.fingerprint_secret', config('app.key'));

        if ($secret === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, $secret);
    }

    /**
     * @param  array<string, mixed>  $signals
     */
    public function normalize(array $signals): string
    {
        $parts = [];

        foreach (['document_type', 'document_number', 'full_name', 'birthdate', 'issuing_organization'] as $key) {
            $value = $signals[$key] ?? null;

            if (! is_scalar($value)) {
                continue;
            }

            $normalized = $this->normalizeScalar((string) $value);

            if ($normalized === '') {
                continue;
            }

            $parts[] = $key.'='.$normalized;
        }

        return implode('|', $parts);
    }

    private function normalizeScalar(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/[^A-Z0-9 .\\-\/]/', '', $value) ?? $value;

        return trim($value);
    }
}
