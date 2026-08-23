<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Throwable;

class AbyipPdfExtractor
{
    public function __construct(private readonly Parser $parser = new Parser) {}

    /**
     * @return array{
     *     year: int,
     *     estimated_budget: float,
     *     sk_fund: float,
     *     total_expenditure: float,
     *     chairperson_name: ?string,
     *     approved_by_name: ?string,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function extract(string $absolutePdfPath): array
    {
        $empty = [
            'year' => (int) now()->year,
            'estimated_budget' => 0.0,
            'sk_fund' => 0.0,
            'total_expenditure' => 0.0,
            'chairperson_name' => null,
            'approved_by_name' => null,
            'rows' => [],
        ];

        try {
            if (! is_file($absolutePdfPath)) {
                Log::warning('ABYIP PDF not found', ['path' => $absolutePdfPath]);

                return $empty;
            }

            $pdf = $this->parser->parseFile($absolutePdfPath);
            $text = $this->normalizeText($pdf->getText());

            if ($text === '') {
                Log::warning('ABYIP PDF produced empty text', ['path' => $absolutePdfPath]);

                return $empty;
            }

            $result = $empty;
            $result['year'] = $this->extractYear($text);
            $result['estimated_budget'] = $this->extractCurrency($text, '/Barangay\s+Estimated\s+Budget\s*[:：]?\s*₱?\s*([\d,]+(?:\.\d{2})?)/i');
            $result['sk_fund'] = $this->extractCurrency($text, '/Sangguniang\s+Kabataan\s+Fund\s*(?:10%)?\s*[:：]?\s*₱?\s*([\d,]+(?:\.\d{2})?)/i');
            $result['total_expenditure'] = $this->extractCurrency($text, '/Total\s+(?:Expenditure|Budget)\s*[:：]?\s*₱?\s*([\d,]+(?:\.\d{2})?)/i');
            $result['chairperson_name'] = $this->extractSignatory($text, 'Prepared by', 'SK Chairperson');
            $result['approved_by_name'] = $this->extractSignatory($text, 'Approved by', 'Barangay Chairman');
            $result['rows'] = $this->extractRows($text);

            if ($result['total_expenditure'] <= 0) {
                $itemTotal = collect($result['rows'])
                    ->where('row_type', 'item')
                    ->sum(fn (array $row) => (float) ($row['total'] ?? 0));

                if ($itemTotal > 0) {
                    $result['total_expenditure'] = $itemTotal;
                }
            }

            $this->warnIfTotalsMismatch($result, $text);

            return $result;
        } catch (Throwable $exception) {
            Log::warning('ABYIP PDF extraction failed', [
                'path' => $absolutePdfPath,
                'message' => $exception->getMessage(),
            ]);

            return $empty;
        }
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function extractYear(string $text): int
    {
        if (preg_match('/ABYIP\)\s*CY\s*(\d{4})/i', $text, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/Calendar\s+Year\s*[:：]?\s*(\d{4})/i', $text, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/CY\s*(\d{4})/i', $text, $matches) === 1) {
            return (int) $matches[1];
        }

        return (int) now()->year;
    }

    private function extractCurrency(string $text, string $pattern): float
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return 0.0;
        }

        return $this->parseAmount($matches[1]);
    }

    private function extractSignatory(string $text, string $label, string $titleHint): ?string
    {
        $pattern = '/'.preg_quote($label, '/').'\s*[:：]?\s*(.+?)(?:\n|$)/iu';

        if (preg_match($pattern, $text, $matches) === 1) {
            $name = trim($matches[1]);

            if ($name !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($titleHint))) {
                return $name;
            }
        }

        $blockPattern = '/'.preg_quote($titleHint, '/').'\s*\n\s*([A-Z][^\n]+)/iu';

        if (preg_match($blockPattern, $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractRows(string $text): array
    {
        $rows = [];
        $lines = preg_split('/\n/u', $text) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if ($this->isSectionHeader($line)) {
                $rows[] = ['row_type' => 'section', 'label' => $this->cleanHeaderLabel($line)];

                continue;
            }

            if ($this->isSubsectionHeader($line)) {
                $rows[] = ['row_type' => 'subsection', 'label' => $this->cleanHeaderLabel($line)];

                continue;
            }

            $item = $this->parseItemLine($line);

            if ($item !== null) {
                $rows[] = $item;
            }
        }

        return $rows;
    }

    private function isSectionHeader(string $line): bool
    {
        if (preg_match('/^(?:[IVXLC]+\.\s+|[A-Z0-9][A-Z0-9\s&,\-\/\(\)]+)$/', $line) !== 1) {
            return false;
        }

        $upperRatio = $this->uppercaseRatio($line);

        return $upperRatio >= 0.75 || preg_match('/^(?:I{1,3}|IV|V|VI{0,3}|IX|X)\.\s+/i', $line) === 1;
    }

    private function isSubsectionHeader(string $line): bool
    {
        return preg_match('/^[A-J]\.\s+[A-Z]/i', $line) === 1
            && ! $this->looksLikeCurrencyRow($line);
    }

    private function cleanHeaderLabel(string $line): string
    {
        return trim(preg_replace('/\s{2,}/u', ' ', $line) ?? $line);
    }

    /**
     * @return ?array<string, mixed>
     */
    private function parseItemLine(string $line): ?array
    {
        if ($this->looksLikeCurrencyRow($line)) {
            return $this->parseCurrencyColumns($line);
        }

        if (preg_match('/^(\d+[\.)]?\s+)?(.{3,120})$/u', $line, $matches) === 1) {
            $ppa = trim($matches[2]);

            if ($this->isSectionHeader($ppa) || $this->isSubsectionHeader($ppa)) {
                return null;
            }

            return [
                'row_type' => 'item',
                'ppa' => $ppa,
                'description' => null,
                'expected_result' => null,
                'performance_indicator' => null,
                'period' => null,
                'mooe' => 0.0,
                'co' => 0.0,
                'total' => 0.0,
                'person_responsible' => null,
            ];
        }

        return null;
    }

    private function looksLikeCurrencyRow(string $line): bool
    {
        return preg_match_all('/₱?\s*[\d,]+\.\d{2}/u', $line) >= 2;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCurrencyColumns(string $line): array
    {
        preg_match_all('/₱?\s*([\d,]+\.\d{2})/u', $line, $amountMatches);
        $amounts = array_map(fn (string $value) => $this->parseAmount($value), $amountMatches[1] ?? []);
        $textPart = trim(preg_replace('/₱?\s*[\d,]+\.\d{2}/u', ' ', $line) ?? $line);
        $textPart = trim(preg_replace('/\s{2,}/u', ' | ', $textPart) ?? $textPart);
        $parts = array_values(array_filter(array_map('trim', explode('|', $textPart))));

        $mooe = $amounts[0] ?? 0.0;
        $co = $amounts[1] ?? 0.0;
        $total = $amounts[2] ?? ($mooe + $co);

        return [
            'row_type' => 'item',
            'ppa' => $parts[0] ?? null,
            'description' => $parts[1] ?? null,
            'expected_result' => $parts[2] ?? null,
            'performance_indicator' => $parts[3] ?? null,
            'period' => $parts[4] ?? null,
            'mooe' => $mooe,
            'co' => $co,
            'total' => $total,
            'person_responsible' => $parts[5] ?? null,
        ];
    }

    private function parseAmount(string $value): float
    {
        return (float) str_replace(',', '', $value);
    }

    private function uppercaseRatio(string $line): float
    {
        $letters = preg_replace('/[^A-Za-z]/u', '', $line) ?? '';

        if ($letters === '') {
            return 0.0;
        }

        $upper = preg_replace('/[^A-Z]/u', '', $letters) ?? '';

        return mb_strlen($upper) / max(mb_strlen($letters), 1);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function warnIfTotalsMismatch(array $result, string $text): void
    {
        $itemTotal = collect($result['rows'])
            ->where('row_type', 'item')
            ->sum(fn (array $row) => (float) ($row['total'] ?? 0));

        if ($itemTotal <= 0) {
            return;
        }

        $printedTotal = $this->extractCurrency($text, '/\bTotal\b\s*₱?\s*([\d,]+(?:\.\d{2})?)/i');
        $expected = (float) ($result['total_expenditure'] ?: $printedTotal);

        if ($expected > 0 && abs($itemTotal - $expected) > 1.0) {
            Log::warning('ABYIP item totals do not match printed total', [
                'item_total' => $itemTotal,
                'printed_total' => $expected,
            ]);
        }
    }
}
