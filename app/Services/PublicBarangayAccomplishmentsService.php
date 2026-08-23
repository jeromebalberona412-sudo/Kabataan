<?php

namespace App\Services;

use App\Models\Accomplishment;
use App\Models\Barangay;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PublicBarangayAccomplishmentsService
{
    /**
     * @return list<int>
     */
    public function barangayIdsWithDocuments(): array
    {
        try {
            return Accomplishment::query()
                ->documents()
                ->distinct()
                ->pluck('barangay_id')
                ->all();
        } catch (QueryException $e) {
            Log::warning('Accomplishments table unavailable (barangayIdsWithDocuments): '.$e->getMessage());

            return [];
        }
    }

    public function hasDocument(Barangay $barangay): bool
    {
        try {
            return Accomplishment::query()
                ->documents()
                ->where('barangay_id', $barangay->id)
                ->exists();
        } catch (QueryException $e) {
            Log::warning('Accomplishments table unavailable (hasDocument): '.$e->getMessage());

            return false;
        }
    }

    public function latestForBarangay(Barangay $barangay): ?object
    {
        try {
            $document = Accomplishment::query()
                ->documents()
                ->where('barangay_id', $barangay->id)
                ->orderByDesc('fiscal_year')
                ->orderByDesc('created_at')
                ->first();
        } catch (QueryException $e) {
            Log::warning('Accomplishments table unavailable (latestForBarangay fetch doc): '.$e->getMessage());

            return null;
        }

        if ($document === null) {
            return null;
        }

        try {
            $lines = Accomplishment::query()
                ->where('document_id', $document->id)
                ->where('row_type', '!=', Accomplishment::ROW_DOCUMENT)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (QueryException $e) {
            Log::warning('Accomplishments table unavailable (latestForBarangay fetch lines): '.$e->getMessage());
            $lines = collect();
        }

        $estimatedBudget = (float) ($document->barangay_estimated_budget ?? 0);
        $skFund = (float) ($document->sk_fund_amount ?? 0);
        $totalExpenditure = (float) ($document->total_budget ?? 0);
        $skFundPercentage = (float) ($document->sk_fund_percentage ?? 10);

        if ($skFund > 1000 && ($estimatedBudget <= 0 || $estimatedBudget < ($skFund / 2))) {
            $estimatedBudget = round($skFund / max($skFundPercentage / 100, 0.1), 2);
        }

        if ($totalExpenditure <= 0 && $skFund > 0) {
            $totalExpenditure = $skFund;
        }

        return (object) [
            'year' => (int) $document->fiscal_year,
            'estimated_budget' => $estimatedBudget,
            'sk_fund' => $skFund,
            'total_expenditure' => $totalExpenditure,
            'chairperson_name' => $document->prepared_by_name ?? $document->prepared_by,
            'chairperson_title' => $document->prepared_by_position ?? $document->prepared_position ?? 'SK Chairperson',
            'approved_by_name' => $document->approved_by_name ?? $document->approved_by,
            'approved_by_title' => $document->approved_by_position ?? $document->approved_position ?? 'Barangay Chairman',
            'status' => $document->status ?? Accomplishment::STATUS_PENDING,
            'items' => $this->buildDisplayItems($lines),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function buildDisplayItems(Collection $lines): Collection
    {
        $items = collect();
        $currentSection = null;

        foreach ($lines as $line) {
            if ($line->row_type === Accomplishment::ROW_EXPENDITURE) {
                if ($currentSection !== 'expenditure') {
                    $items->push((object) [
                        'row_type' => 'section',
                        'label' => 'General Administration Program (Expenditure)',
                    ]);
                    $currentSection = 'expenditure';
                }

                $items->push($this->mapItemRow($line));

                continue;
            }

            if ($line->row_type === Accomplishment::ROW_YOUTH_PROGRAM) {
                if ($currentSection !== 'youth') {
                    $items->push((object) [
                        'row_type' => 'section',
                        'label' => 'SK Youth Development and Empowerment Programs',
                    ]);
                    $currentSection = 'youth';
                }

                $label = trim(($line->code ? $line->code.'. ' : '').($line->program_name ?? ''));

                if ($label !== '') {
                    $items->push((object) [
                        'row_type' => 'subsection',
                        'label' => $label,
                    ]);
                }

                continue;
            }

            if ($line->row_type === Accomplishment::ROW_ACTIVITY) {
                $items->push($this->mapItemRow($line));
            }
        }

        return $items;
    }

    private function mapItemRow(Accomplishment $line): object
    {
        $ppa = trim((string) ($line->activity_name ?: $line->program_name ?: ''));
        $mooe = (float) ($line->mooe ?? 0);
        $co = (float) ($line->co ?? 0);
        $total = (float) ($line->total ?? 0);

        if ($total <= 0 && ($mooe > 0 || $co > 0)) {
            $total = round($mooe + $co, 2);
        }

        return (object) [
            'row_type' => 'item',
            'ppa' => $ppa !== '' ? $ppa : null,
            'description' => $line->description,
            'expected_result' => $line->expected_result,
            'performance_indicator' => $line->performance_indicator,
            'period' => $this->formatPeriod($line),
            'mooe' => $mooe,
            'co' => $co,
            'total' => $total,
            'person_responsible' => $line->person_responsible,
        ];
    }

    private function formatPeriod(Accomplishment $line): ?string
    {
        $start = $line->implementation_start ?? $line->implementation_period ?? null;
        $end = $line->implementation_end ?? null;

        if ($start && $end) {
            return trim($start.' – '.$end);
        }

        return $start ?: ($end ?: null);
    }
}
