<?php

namespace App\Services;

class PhilippineIdAddressParser
{
    /**
     * @param  list<array{text: string, confidence?: float}>  $lines
     * @return array{
     *     success: bool,
     *     message?: string,
     *     address?: string|null,
     *     barangay?: string|null,
     *     city?: string|null,
     *     province?: string|null
     * }
     */
    public function parse(array $lines, string $fullText = ''): array
    {
        $joined = trim($fullText) !== '' ? $fullText : $this->joinLines($lines);

        if ($joined === '') {
            return [
                'success' => false,
                'message' => 'Address not found.',
            ];
        }

        $addressBlock = $this->extractAddressBlock($lines, $joined);

        if ($addressBlock === null) {
            return [
                'success' => false,
                'message' => 'Address not found.',
                'address' => null,
                'barangay' => null,
                'city' => null,
                'province' => null,
            ];
        }

        $parsed = $this->parseAddressComponents($addressBlock);

        if ($parsed['barangay'] === null && $parsed['city'] === null && $parsed['province'] === null) {
            return [
                'success' => false,
                'message' => 'Address not found.',
                'address' => $addressBlock,
                'barangay' => null,
                'city' => null,
                'province' => null,
            ];
        }

        return [
            'success' => true,
            'address' => $addressBlock,
            'barangay' => $parsed['barangay'],
            'city' => $parsed['city'],
            'province' => $parsed['province'],
        ];
    }

    /**
     * @param  list<array{text: string, confidence?: float}>  $lines
     */
    private function joinLines(array $lines): string
    {
        return trim(implode(' ', array_map(
            fn (array $line) => trim((string) ($line['text'] ?? '')),
            $lines
        )));
    }

    /**
     * @param  list<array{text: string, confidence?: float}>  $lines
     */
    private function extractAddressBlock(array $lines, string $joined): ?string
    {
        if (preg_match('/address\s*[:\-]?\s*(.+?)(?:cell\s*no|contact|tel|phone|important|signature|parent|guardian|$)/i', $joined, $match)) {
            return $this->cleanAddress($match[1]);
        }

        if (preg_match('/(?:parent|guardian)\s*(?:address|\'s\s*address)?\s*[:\-]?\s*(.+?)(?:cell\s*no|contact|tel|phone|important|signature|$)/i', $joined, $match)) {
            return $this->cleanAddress($match[1]);
        }

        foreach ($lines as $index => $line) {
            $text = trim((string) ($line['text'] ?? ''));

            if (! preg_match('/^address\s*[:\-]?$/i', $text)) {
                continue;
            }

            $parts = [];

            for ($i = $index + 1; $i < count($lines); $i++) {
                $next = trim((string) ($lines[$i]['text'] ?? ''));

                if ($next === '' || preg_match('/^(cell\s*no|contact|important|parent|guardian)/i', $next)) {
                    break;
                }

                $parts[] = $next;
            }

            if ($parts !== []) {
                return $this->cleanAddress(implode(' ', $parts));
            }
        }

        if (preg_match('/\b(brgy\.?|barangay|sitio|purok|blk|block|lot)\b/i', $joined)) {
            return $this->cleanAddress($joined);
        }

        foreach ($lines as $line) {
            $text = trim((string) ($line['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if (preg_match('/\b\d+\s+(?:sitio|purok|zone)\b/i', $text)) {
                return $this->cleanAddress($text);
            }

            if (preg_match('/\b(?:sitio|purok|brgy|barangay)\b/i', $text)
                && preg_match('/\b(?:santa\s*cruz|sta\.?\s*cruz|laguna|lag\.?)\b/i', $text)) {
                return $this->cleanAddress($text);
            }

            if (preg_match('/\b(?:brgy\.?|barangay)\s+[A-Za-z0-9.\-\s]+/i', $text)
                && preg_match('/\b(?:santa\s*cruz|sta\.?\s*cruz|laguna|lag\.?)\b/i', $joined)) {
                return $this->cleanAddress($text);
            }
        }

        return null;
    }

    /**
     * @return array{barangay: ?string, city: ?string, province: ?string}
     */
    private function parseAddressComponents(string $address): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($address)) ?? $address;

        $barangay = null;
        if (preg_match('/(?:brgy\.?|barangay)\s+([A-Za-z0-9.\-\s]+?)(?:,|\s+(?:sta\.?|santa|san|city|municipality)|$)/i', $normalized, $match)) {
            $barangay = trim($match[1]);
        } elseif (preg_match('/\b(?:sitio|purok)\s+([A-Za-z0-9.\-\s]+)/i', $normalized, $match)) {
            $barangay = trim($match[1]);
        } elseif (preg_match('/\b([A-Za-z][A-Za-z\s.\-]{2,})\s+(?:sta\.?\s*cruz|santa\s*cruz)\b/i', $normalized, $match)) {
            $barangay = trim($match[1]);
        } elseif (preg_match('/\b(?:sitio|purok)\s+\d+\s+([A-Za-z][A-Za-z\s.\-]+?)\s+(?:sta\.?\s*cruz|santa\s*cruz|lag\.?|laguna)\b/i', $normalized, $match)) {
            $barangay = trim($match[1]);
        }

        $city = null;
        if (preg_match('/\b((?:sta\.?|santa)\s*cruz)\b/i', $normalized, $match)) {
            $city = 'Santa Cruz';
        } elseif (preg_match('/\b([A-Za-z][A-Za-z\s]+?\s+city)\b/i', $normalized, $match)) {
            $city = trim($match[1]);
        } elseif (preg_match('/\b(san\s+[A-Za-z\s]+?)\s+(?:city|laguna|lag\.?)\b/i', $normalized, $match)) {
            $city = trim($match[1]).' City';
        }

        $province = null;
        if (preg_match('/\b(laguna|lag\.?)\b/i', $normalized)) {
            $province = 'Laguna';
        } elseif (preg_match('/\b([A-Za-z][A-Za-z\s]+)\s*$/', $normalized, $match)) {
            $candidate = trim($match[1]);
            if (! preg_match('/\b(cruz|city|sitio|purok|brgy|barangay)\b/i', $candidate)) {
                $province = $candidate;
            }
        }

        if ($barangay !== null && strcasecmp($barangay, 'Santa Cruz') === 0) {
            $barangay = null;
        }

        return [
            'barangay' => $barangay,
            'city' => $city,
            'province' => $province,
        ];
    }

    private function cleanAddress(string $value): string
    {
        $cleaned = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        return rtrim($cleaned, '.,;');
    }
}
