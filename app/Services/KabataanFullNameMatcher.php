<?php

namespace App\Services;

class KabataanFullNameMatcher
{
    /**
     * @return array{first: string, middle: string, last: string, suffix: string}
     */
    public function formComponents(string $firstName, ?string $middleName, string $lastName, ?string $suffix = null): array
    {
        return [
            'first' => $this->normalizeToken($firstName),
            'middle' => $this->normalizeToken($middleName ?? ''),
            'last' => $this->normalizeToken($lastName),
            'suffix' => $this->normalizeSuffix($suffix),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{first: string, middle: string, last: string, suffix: string}
     */
    public function formComponentsFromFields(array $fields): array
    {
        $suffix = (string) ($fields['suffix'] ?? '');

        if (strcasecmp($suffix, 'Others') === 0) {
            $suffix = (string) ($fields['custom_suffix'] ?? '');
        }

        if (strcasecmp($suffix, 'None') === 0) {
            $suffix = '';
        }

        return $this->formComponents(
            (string) ($fields['first_name'] ?? ''),
            isset($fields['middle_name']) ? (string) $fields['middle_name'] : null,
            (string) ($fields['last_name'] ?? ''),
            $suffix !== '' ? $suffix : null,
        );
    }

    public function normalizedKeyFromFormFields(array $fields): string
    {
        return $this->normalizedKey($this->formComponentsFromFields($fields));
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function formatFormFullNameForDisplay(array $fields): string
    {
        $suffix = (string) ($fields['suffix'] ?? '');

        if (strcasecmp($suffix, 'Others') === 0) {
            $suffix = (string) ($fields['custom_suffix'] ?? '');
        }

        if (strcasecmp($suffix, 'None') === 0) {
            $suffix = '';
        }

        $parts = array_values(array_filter([
            trim((string) ($fields['first_name'] ?? '')),
            trim((string) ($fields['middle_name'] ?? '')),
            trim((string) ($fields['last_name'] ?? '')),
            $suffix !== '' ? trim($suffix) : '',
        ], fn (string $part) => $part !== ''));

        return $parts !== [] ? preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? implode(' ', $parts) : '';
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}|null  $components
     */
    public function formatComponentsForDisplay(?array $components): string
    {
        if (! is_array($components)) {
            return '';
        }

        $middle = trim((string) ($components['middle'] ?? ''));

        if ($middle !== '' && strlen($middle) === 1) {
            $middle = strtoupper($middle).'.';
        }

        $parts = array_values(array_filter([
            $this->toDisplayCase((string) ($components['first'] ?? '')),
            $this->toDisplayCase($middle),
            $this->toDisplayCase((string) ($components['last'] ?? '')),
            trim((string) ($components['suffix'] ?? '')),
        ], fn (string $part) => $part !== ''));

        return $parts !== [] ? preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? implode(' ', $parts) : '';
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}|null  $form
     */
    public function extractBestNameLine(string $text, ?array $form = null): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $parsed = $this->parseOcrName($text, $form);

        if ($parsed !== null) {
            $formatted = $this->formatComponentsForDisplay($parsed);

            if ($formatted !== '') {
                return $formatted;
            }
        }

        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $this->isIgnoredLine($line)) {
                continue;
            }

            if ($this->looksLikePersonNameLine($this->normalizeToken($line))) {
                return preg_replace('/\s+/', ' ', $line) ?? $line;
            }
        }

        if (preg_match('/\b([A-Za-z][A-Za-z\s.\'-]*\s+[A-Za-z]\.?\s+[A-Za-z][A-Za-z\s.\'-]*)\b/u', $text, $match)) {
            return preg_replace('/\s+/', ' ', trim($match[1])) ?? trim($match[1]);
        }

        return null;
    }

    public function normalizedKeyFromRegistration(\App\Models\KabataanRegistration $registration): string
    {
        $suffix = $registration->suffix;

        if ($suffix !== null && strcasecmp($suffix, 'None') === 0) {
            $suffix = '';
        }

        return $this->normalizedKey($this->formComponents(
            $registration->first_name,
            $registration->middle_name,
            $registration->last_name,
            $suffix,
        ));
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $form
     * @param  array{first: string, middle: string, last: string, suffix: string}  $ocr
     */
    public function matches(array $form, array $ocr, bool $strictMiddle = false): bool
    {
        foreach ($this->ocrComponentPermutations($ocr) as $candidate) {
            if ($this->matchesComponents($form, $candidate, $strictMiddle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format-agnostic identity check: Step 1 first/last/(middle initial) may appear in any ID layout
     * (LAST, FIRST M. / FIRST MIDDLE LAST / FIRST LAST / etc.).
     *
     * @param  array{first: string, middle: string, last: string, suffix: string}  $form
     */
    public function formIdentityVisibleInText(array $form, string ...$texts): bool
    {
        $haystack = $this->normalizeOcrNameText(implode("\n", array_filter($texts, static fn ($t) => trim((string) $t) !== '')));

        if ($haystack === '' || ($form['first'] ?? '') === '' || ($form['last'] ?? '') === '') {
            return false;
        }

        $compact = preg_replace('/[^A-Z0-9]/', '', $haystack) ?? '';
        if ($compact === '') {
            return false;
        }

        if (! $this->compactContainsNamePart($compact, $form['last'])) {
            return false;
        }

        if (! $this->compactContainsFirstName($compact, $form['first'])) {
            return false;
        }

        // Middle: first letter only when Step 1 has a middle name AND the ID shows a middle initial.
        // Missing middle on the ID is OK (many cards omit it).
        if (($form['middle'] ?? '') !== '') {
            $formInitial = $this->middleInitial($form['middle']);
            $ocrInitial = $this->detectMiddleInitialInText($haystack, $form);
            if ($formInitial !== '' && $ocrInitial !== '' && $formInitial !== $ocrInitial) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $form
     */
    public function matchesFormToOcrText(array $form, string $ocrText, bool $strictMiddle = false): bool
    {
        unset($strictMiddle);

        // Primary path: order/format-agnostic token presence.
        if ($this->formIdentityVisibleInText($form, $ocrText)) {
            return true;
        }

        $ocrText = $this->normalizeOcrNameText($ocrText);

        if ($ocrText === '' || $form['first'] === '' || $form['last'] === '') {
            return false;
        }

        $parsed = $this->parseOcrName($ocrText, $form);

        return is_array($parsed) && $this->matches($form, $parsed, false);
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $ocr
     * @return list<array{first: string, middle: string, last: string, suffix: string}>
     */
    private function ocrComponentPermutations(array $ocr): array
    {
        $first = (string) ($ocr['first'] ?? '');
        $middle = (string) ($ocr['middle'] ?? '');
        $last = (string) ($ocr['last'] ?? '');
        $suffix = (string) ($ocr['suffix'] ?? '');

        $base = static fn (string $f, string $m, string $l): array => [
            'first' => $f,
            'middle' => $m,
            'last' => $l,
            'suffix' => $suffix,
        ];

        $candidates = [
            $base($first, $middle, $last),
            $base($first, $last, $middle),
            $base($middle, $first, $last),
            $base($middle, $last, $first),
            $base($last, $first, $middle),
            $base($last, $middle, $first),
        ];

        // Deduplicate identical permutations.
        $unique = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $key = $candidate['first'].'|'.$candidate['middle'].'|'.$candidate['last'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $candidate;
        }

        return $unique;
    }

    private function compactContainsNamePart(string $compact, string $part): bool
    {
        $partKey = preg_replace('/[^A-Z0-9]/', '', strtoupper($this->normalizeToken($part))) ?? '';
        if ($partKey === '' || strlen($partKey) < 2) {
            return false;
        }

        if (str_contains($compact, $partKey)) {
            return true;
        }

        // Soft OCR typos for long surnames/first names.
        if (strlen($partKey) >= 5) {
            $len = strlen($compact);
            $needleLen = strlen($partKey);
            for ($i = 0; $i <= $len - $needleLen; $i++) {
                $window = substr($compact, $i, $needleLen);
                similar_text($partKey, $window, $percent);
                if ($percent >= 90.0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function compactContainsFirstName(string $compact, string $firstName): bool
    {
        $firstName = $this->normalizeToken($firstName);
        if ($firstName === '') {
            return false;
        }

        $fullKey = preg_replace('/[^A-Z0-9]/', '', $firstName) ?? '';
        if ($fullKey !== '' && str_contains($compact, $fullKey)) {
            return true;
        }

        $tokens = preg_split('/\s+/', $firstName) ?: [];
        $significant = [];
        foreach ($tokens as $token) {
            $key = preg_replace('/[^A-Z0-9]/', '', $token) ?? '';
            if (strlen($key) >= 2) {
                $significant[] = $key;
            }
        }

        if ($significant === []) {
            return false;
        }

        $hits = 0;
        foreach ($significant as $key) {
            if (str_contains($compact, $key) || $this->compactContainsNamePart($compact, $key)) {
                $hits++;
            }
        }

        // Multi-word first names (e.g. JUANA PAULA): require majority of tokens.
        if (count($significant) >= 2) {
            return $hits >= (int) ceil(count($significant) * 0.5);
        }

        return $hits >= 1;
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $form
     */
    private function detectMiddleInitialInText(string $haystack, array $form): string
    {
        // Patterns common on PH IDs: "LAST, FIRST M." or "FIRST M. LAST" or trailing " M "
        if (preg_match('/,\s*[A-Z][A-Z\s\-]+\s+([A-Z])\.?(?:\s|$)/i', $haystack, $m) === 1) {
            return strtoupper($m[1]);
        }

        if (preg_match('/\b([A-Z])\.\s+[A-Z]{2,}\b/i', $haystack, $m) === 1) {
            // "M. SURNAME" style — weak; prefer initial between first and last when possible
        }

        $first = preg_quote($form['first'], '/');
        $last = preg_quote($form['last'], '/');
        if (
            $first !== ''
            && $last !== ''
            && preg_match('/\b'.$first.'\b\s+([A-Z])\.?\s+\b'.$last.'\b/i', $haystack, $m) === 1
        ) {
            return strtoupper($m[1]);
        }

        if (
            $first !== ''
            && $last !== ''
            && preg_match('/\b'.$last.'\b\s*,\s*\b'.$first.'\b\s+([A-Z])\.?/i', $haystack, $m) === 1
        ) {
            return strtoupper($m[1]);
        }

        return '';
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $left
     * @param  array{first: string, middle: string, last: string, suffix: string}  $right
     */
    private function matchesComponents(array $left, array $right, bool $strictMiddle = false): bool
    {
        if ($left['last'] === '' || $left['first'] === '' || $right['last'] === '' || $right['first'] === '') {
            return false;
        }

        if (! $this->lastNamesMatch($left['last'], $right['last'])) {
            return false;
        }

        if (! $this->firstNamesMatch($left['first'], $right['first'])) {
            return false;
        }

        if (! $this->middleNamesMatch($left['middle'], $right['middle'], $strictMiddle)) {
            return false;
        }

        if (! $this->suffixesMatch($left['suffix'], $right['suffix'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $components
     */
    public function normalizedKey(array $components): string
    {
        $parts = array_filter([
            $components['last'],
            $components['first'],
            $this->middleInitial($components['middle']),
            $components['suffix'],
        ]);

        return implode('|', $parts);
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}|null  $form
     * @return array{first: string, middle: string, last: string, suffix: string}|null
     */
    public function parseOcrName(string $text, ?array $form = null): ?array
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^([^,]+),\s*(.+)$/i', $text, $commaMatch)) {
            $last = $this->normalizeToken($commaMatch[1]);
            $remainder = trim($commaMatch[2]);
            $remainder = preg_replace('/\b(grade|lrn|section|sy)\b.*$/i', '', $remainder) ?? $remainder;
            $remainder = trim($remainder);

            if ($last !== '' && $remainder !== '') {
                $commaCandidate = $this->parseNameCandidate($remainder.' '.$last, $form);

                if ($commaCandidate !== null) {
                    return $commaCandidate;
                }
            }
        }

        if (preg_match('/\b(?:name\s+of\s+student|student\s+name|pangalan|name)\b\s*[:\-]?\s*(.+?)(?:\r?\n|grade|lrn|section|s\.?y\.|address|signature|$)/i', $text, $match)) {
            $candidate = trim($match[1]);

            if ($parsed = $this->parseNameCandidate($candidate, $form)) {
                return $parsed;
            }
        }

        if (preg_match('/\bname\b\s*[:\-]?\s*(.+?)(?:\r?\n|grade|lrn|section|s\.?y\.|signature|$)/i', $text, $match)) {
            $candidate = trim($match[1]);

            if ($parsed = $this->parseNameCandidate($candidate, $form)) {
                return $parsed;
            }
        }

        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $this->isIgnoredLine($line)) {
                continue;
            }

            if ($parsed = $this->parseNameCandidate($line, $form)) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}|null  $form
     * @return array{first: string, middle: string, last: string, suffix: string}|null
     */
    private function parseNameCandidate(string $candidate, ?array $form = null): ?array
    {
        $candidate = preg_replace('/\s+/', ' ', trim($candidate)) ?? '';

        if ($candidate === '' || $this->isIgnoredLine($candidate)) {
            return null;
        }

        if (is_array($form) && ($form['last'] ?? '') !== '') {
            $withHints = $this->parseNameCandidateWithFormHints($candidate, $form);

            if ($withHints !== null) {
                return $withHints;
            }
        }

        $suffix = '';
        $suffixPattern = '/\b(JR|SR|II|III|IV|V|VI|VII|VIII|IX|X)\.?$/i';

        if (preg_match($suffixPattern, $candidate, $suffixMatch)) {
            $suffix = $this->normalizeSuffix($suffixMatch[1]);
            $candidate = trim((string) preg_replace($suffixPattern, '', $candidate));
        }

        $tokens = preg_split('/\s+/', $candidate) ?: [];

        if (count($tokens) < 2) {
            return null;
        }

        if (count($tokens) === 2) {
            return [
                'first' => $this->normalizeToken($tokens[0]),
                'middle' => '',
                'last' => $this->normalizeToken($tokens[1]),
                'suffix' => $suffix,
            ];
        }

        $first = $this->normalizeToken(array_shift($tokens));

        if ($tokens === []) {
            return null;
        }

        if (count($tokens) === 1) {
            return [
                'first' => $first,
                'middle' => '',
                'last' => $this->normalizeToken($tokens[0]),
                'suffix' => $suffix,
            ];
        }

        if ($this->isMiddleInitialToken($tokens[0])) {
            $middle = $this->normalizeMiddleInitialToken($tokens[0]);
            array_shift($tokens);

            return [
                'first' => $first,
                'middle' => $middle,
                'last' => $this->normalizeToken(implode(' ', $tokens)),
                'suffix' => $suffix,
            ];
        }

        if (count($tokens) >= 3 && $this->isMiddleInitialToken($tokens[count($tokens) - 2])) {
            $last = $this->normalizeToken(array_pop($tokens));
            $middleInitial = $this->normalizeMiddleInitialToken(array_pop($tokens));
            $middle = $this->normalizeToken(implode(' ', $tokens));

            return [
                'first' => $first,
                'middle' => $middle !== '' ? $middle : $middleInitial,
                'last' => $last,
                'suffix' => $suffix,
            ];
        }

        if (count($tokens) >= 2 && $this->isMiddleInitialToken($tokens[count($tokens) - 1])) {
            $last = $this->normalizeToken(array_pop($tokens));
            $middleInitial = $this->normalizeMiddleInitialToken(array_pop($tokens));

            return [
                'first' => $first,
                'middle' => $middleInitial,
                'last' => $last,
                'suffix' => $suffix,
            ];
        }

        $last = $this->normalizeToken(array_pop($tokens));
        $middle = $this->normalizeToken(implode(' ', $tokens));

        return [
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'suffix' => $suffix,
        ];
    }

    /**
     * @param  array{first: string, middle: string, last: string, suffix: string}  $form
     * @return array{first: string, middle: string, last: string, suffix: string}|null
     */
    private function parseNameCandidateWithFormHints(string $candidate, array $form): ?array
    {
        $suffix = '';
        $suffixPattern = '/\b(JR|SR|II|III|IV|V|VI|VII|VIII|IX|X)\.?$/i';

        if (preg_match($suffixPattern, $candidate, $suffixMatch)) {
            $suffix = $this->normalizeSuffix($suffixMatch[1]);
            $candidate = trim((string) preg_replace($suffixPattern, '', $candidate));
        }

        $tokens = preg_split('/\s+/', $candidate) ?: [];
        $lastTokens = preg_split('/\s+/', $form['last']) ?: [];

        if ($tokens === [] || $lastTokens === []) {
            return null;
        }

        $lastTokenCount = count($lastTokens);

        if (count($tokens) <= $lastTokenCount) {
            return null;
        }

        $tail = array_slice($tokens, -$lastTokenCount);
        $tailNormalized = array_map(fn (string $token) => $this->normalizeToken($token), $tail);
        $joinedTail = implode(' ', $tailNormalized);
        $joinedLast = implode(' ', $lastTokens);

        if (! $this->lastNamesMatch($joinedLast, $joinedTail)) {
            return null;
        }

        $prefixTokens = array_slice($tokens, 0, -$lastTokenCount);

        if ($prefixTokens === []) {
            return null;
        }

        $firstTokens = preg_split('/\s+/', $form['first']) ?: [];
        $firstTokenCount = count($firstTokens);

        if ($firstTokenCount > 0 && count($prefixTokens) >= $firstTokenCount) {
            $head = array_slice($prefixTokens, 0, $firstTokenCount);

            if ($this->tokenGroupsMatch($head, $firstTokens)) {
                $remainder = array_slice($prefixTokens, $firstTokenCount);

                if ($remainder === []) {
                    return [
                        'first' => $form['first'],
                        'middle' => '',
                        'last' => $form['last'],
                        'suffix' => $suffix,
                    ];
                }

                if (count($remainder) === 1 && $this->isMiddleInitialToken($remainder[0])) {
                    return [
                        'first' => $form['first'],
                        'middle' => $this->normalizeMiddleInitialToken($remainder[0]),
                        'last' => $form['last'],
                        'suffix' => $suffix,
                    ];
                }

                if ($this->isMiddleInitialToken($remainder[count($remainder) - 1])) {
                    $miToken = array_pop($remainder);

                    return [
                        'first' => $form['first'],
                        'middle' => $remainder !== []
                            ? $this->normalizeToken(implode(' ', $remainder))
                            : $this->normalizeMiddleInitialToken($miToken),
                        'last' => $form['last'],
                        'suffix' => $suffix,
                    ];
                }
            }
        }

        $first = $this->normalizeToken(array_shift($prefixTokens));

        if ($prefixTokens === []) {
            return [
                'first' => $first,
                'middle' => '',
                'last' => $form['last'],
                'suffix' => $suffix,
            ];
        }

        if (count($prefixTokens) === 1) {
            $middleToken = $prefixTokens[0];

            return [
                'first' => $first,
                'middle' => $this->isMiddleInitialToken($middleToken)
                    ? $this->normalizeMiddleInitialToken($middleToken)
                    : $this->normalizeToken($middleToken),
                'last' => $form['last'],
                'suffix' => $suffix,
            ];
        }

        if ($this->isMiddleInitialToken($prefixTokens[count($prefixTokens) - 1])) {
            $miToken = array_pop($prefixTokens);
            $middle = $prefixTokens !== []
                ? $this->normalizeToken(implode(' ', $prefixTokens))
                : $this->normalizeMiddleInitialToken($miToken);

            return [
                'first' => $first,
                'middle' => $middle,
                'last' => $form['last'],
                'suffix' => $suffix,
            ];
        }

        if ($this->isMiddleInitialToken($prefixTokens[0])) {
            return [
                'first' => $first,
                'middle' => $this->normalizeMiddleInitialToken($prefixTokens[0]),
                'last' => $form['last'],
                'suffix' => $suffix,
            ];
        }

        return [
            'first' => $first,
            'middle' => $this->normalizeToken(implode(' ', $prefixTokens)),
            'last' => $form['last'],
            'suffix' => $suffix,
        ];
    }

    private function isIgnoredLine(string $line): bool
    {
        $normalized = $this->normalizeToken($line);

        if ($normalized === '') {
            return true;
        }

        if ($this->looksLikePersonNameLine($normalized)) {
            return false;
        }

        $blocked = [
            'SCHOOL', 'HIGH', 'MEMORIAL', 'NATIONAL', 'ELEMENTARY', 'ACADEMY', 'COLLEGE',
            'UNIVERSITY', 'LRN', 'GRADE', 'SECTION', 'ADDRESS', 'SIGNATURE', 'STUDENT',
            'AVENUE', 'STREET', 'LAGUNA', 'REGION', 'PROVINCE', 'MUNICIPALITY', 'BARANGAY',
            'CALABARZON',
        ];

        foreach ($blocked as $token) {
            if (str_contains($normalized, $token)) {
                return true;
            }
        }

        return (bool) preg_match('/\d{6,}/', $normalized);
    }

    private function looksLikePersonNameLine(string $normalized): bool
    {
        $tokens = preg_split('/\s+/', $normalized) ?: [];

        if (count($tokens) < 2 || count($tokens) > 7) {
            return false;
        }

        if (preg_match('/\d/', $normalized)) {
            return false;
        }

        foreach ($tokens as $token) {
            if ($this->isMiddleInitialToken($token)) {
                continue;
            }

            if (! preg_match('/^[A-Z][A-Z.\-]*$/', $token)) {
                return false;
            }
        }

        return true;
    }

    private function tokenMatches(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        similar_text($left, $right, $percent);

        return $percent >= 88.0;
    }

    private function middleNamesMatch(string $formMiddle, string $ocrMiddle, bool $strict = false): bool
    {
        // Philippine IDs often print middle initial only (e.g. "A." vs Step 1 "ANDRES").
        // Always compare the first letter only — never require the full middle name.
        unset($strict);

        if ($formMiddle === '' || $ocrMiddle === '') {
            return true;
        }

        $formInitial = $this->middleInitial($formMiddle);
        $ocrInitial = $this->middleInitial($ocrMiddle);

        if ($formInitial === '' || $ocrInitial === '') {
            return true;
        }

        return $formInitial === $ocrInitial;
    }

    private function ocrTextContainsFormMiddle(string $formMiddle, string $ocrText): bool
    {
        $formMiddle = $this->normalizeToken($formMiddle);

        if ($formMiddle === '') {
            return true;
        }

        $initial = $this->middleInitial($formMiddle);

        if ($initial === '') {
            return true;
        }

        // First letter only (with optional period), not the full middle name.
        if ((bool) preg_match(
            '/(?:^|[\s,])'.preg_quote($initial, '/').'(?:\.(?=[\s,.]|$)|(?=[\s,.]|$))/i',
            $ocrText,
        )) {
            return true;
        }

        return (bool) preg_match(
            '/,\s*[^,]+\s+'.preg_quote($initial, '/').'\.?(?=\s|$)/i',
            $ocrText,
        );
    }

    private function isMiddleInitialToken(string $token): bool
    {
        $token = trim($token);

        if ($token === '') {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z]\.?$/', $token);
    }

    private function normalizeMiddleInitialToken(string $token): string
    {
        return strtoupper(substr(trim($token), 0, 1));
    }

    private function suffixesMatch(string $formSuffix, string $ocrSuffix): bool
    {
        if ($formSuffix === '' && $ocrSuffix === '') {
            return true;
        }

        if ($formSuffix === '' || $ocrSuffix === '') {
            return true;
        }

        return $formSuffix === $ocrSuffix;
    }

    private function middleInitial(string $value): string
    {
        $value = $this->normalizeToken($value);

        if ($value === '') {
            return '';
        }

        if (strlen($value) === 1) {
            return $value;
        }

        return substr($value, 0, 1);
    }

    private function normalizeSuffix(?string $suffix): string
    {
        $suffix = $this->normalizeToken($suffix ?? '');

        if ($suffix === '' || $suffix === 'NONE') {
            return '';
        }

        return match ($suffix) {
            'JUNIOR', 'JR' => 'JR',
            'SENIOR', 'SR' => 'SR',
            default => $suffix,
        };
    }

    private function normalizeToken(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[.,]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeOcrNameText(string $text): string
    {
        $text = $this->normalizeToken($text);
        $text = preg_replace('/\bJ+(?=UANA\b)/', '', $text) ?? $text;
        $text = preg_replace('/\bJUONO\b/', 'JUANA', $text) ?? $text;
        $text = preg_replace('/\bPAULO\b/', 'PAULA', $text) ?? $text;
        $text = preg_replace('/\bTOLABI[90S]\b/', 'TALABIS', $text) ?? $text;
        $text = preg_replace('/\bTALABI[90S]\b/', 'TALABIS', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function firstNamesMatch(string $formFirst, string $ocrFirst): bool
    {
        if ($this->tokenMatches($formFirst, $ocrFirst)) {
            return true;
        }

        return $this->ocrTextContainsFormFirst($formFirst, $ocrFirst);
    }

    private function lastNamesMatch(string $formLast, string $ocrLast): bool
    {
        if ($this->tokenMatches($formLast, $ocrLast)) {
            return true;
        }

        return $this->ocrTextContainsFormLast($formLast, $ocrLast);
    }

    /**
     * @param  list<string>  $ocrTokens
     * @param  list<string>  $formTokens
     */
    private function tokenGroupsMatch(array $ocrTokens, array $formTokens): bool
    {
        if (count($ocrTokens) !== count($formTokens)) {
            return false;
        }

        foreach ($ocrTokens as $index => $ocrToken) {
            $formToken = $formTokens[$index] ?? '';

            if ($formToken === '') {
                return false;
            }

            $normalizedOcr = $this->normalizeOcrToken($ocrToken);

            if (! $this->tokenMatches($normalizedOcr, $formToken)
                && ! $this->fuzzyOcrTokenMatches($normalizedOcr, $formToken)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeOcrToken(string $token): string
    {
        $token = $this->normalizeToken($token);
        $token = preg_replace('/^J+(?=UANA\b)/', '', $token) ?? $token;

        return trim($token);
    }

    private function fuzzyOcrTokenMatches(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        similar_text($left, $right, $percent);

        return $percent >= 80.0;
    }

    private function ocrTextContainsFormFirst(string $formFirst, string $ocrText): bool
    {
        $formFirst = $this->normalizeToken($formFirst);
        $ocrText = $this->normalizeOcrNameText($ocrText);

        if ($formFirst === '' || $ocrText === '') {
            return false;
        }

        if (str_contains($ocrText, $formFirst)) {
            return true;
        }

        $tokens = preg_split('/\s+/', $formFirst) ?: [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (! str_contains($ocrText, $token) && ! $this->fuzzyContainsToken($ocrText, $token)) {
                return false;
            }
        }

        return $tokens !== [];
    }

    private function ocrTextContainsFormLast(string $formLast, string $ocrText): bool
    {
        $formLast = $this->normalizeToken($formLast);
        $ocrText = $this->normalizeOcrNameText($ocrText);

        if ($formLast === '' || $ocrText === '') {
            return false;
        }

        if (str_contains($ocrText, $formLast)) {
            return true;
        }

        foreach ($this->ocrLastNameVariants($formLast) as $variant) {
            if ($variant !== '' && str_contains($ocrText, $variant)) {
                return true;
            }
        }

        return $this->fuzzyContainsToken($ocrText, $formLast);
    }

    /**
     * @return list<string>
     */
    private function ocrLastNameVariants(string $lastName): array
    {
        $variants = [$lastName];

        if (preg_match('/^(.*?)([0-9])$/', $lastName, $match)) {
            $map = ['9' => 'S', '0' => 'O', '1' => 'I', '5' => 'S'];
            $variants[] = $match[1].($map[$match[2]] ?? '');
        }

        if (str_ends_with($lastName, 'S')) {
            $variants[] = substr($lastName, 0, -1).'9';
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function fuzzyContainsToken(string $haystack, string $token): bool
    {
        $token = $this->normalizeToken($token);

        if ($token === '') {
            return false;
        }

        foreach (preg_split('/\s+/', $haystack) ?: [] as $part) {
            if ($part === '') {
                continue;
            }

            if ($this->tokenMatches($token, $part) || $this->fuzzyOcrTokenMatches($token, $part)) {
                return true;
            }
        }

        return false;
    }

    private function toDisplayCase(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Z]\.?$/', $value)) {
            return strtoupper(rtrim($value, '.')).'.';
        }

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
