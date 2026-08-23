<?php

namespace App\Modules\Baranggay_ABYIP\Services;

use App\Models\Abyip;
use App\Models\Barangay;
use App\Models\BarangayAbyip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Baranggay_ABYIPService
{
    public function barangayIdsWithPublicPdf(): array
    {
        $ids = [];

        if (Schema::hasTable('abyip')) {
            $query = Abyip::query()->documents();

            if (Schema::hasColumn('abyip', 'status')) {
                $query->where('status', Abyip::STATUS_APPROVED);
            }

            if (Schema::hasColumn('abyip', 'pdf_data')) {
                $query->whereNotNull('pdf_data')->where('pdf_data', '!=', '');
            }

            $ids = $query->pluck('barangay_id')->unique()->map(fn ($id) => (int) $id)->all();
        }

        if (Schema::hasTable('abyips') && Schema::hasColumn('abyips', 'source_pdf_path')) {
            $legacyIds = BarangayAbyip::query()
                ->whereNotNull('source_pdf_path')
                ->where('source_pdf_path', '!=', '')
                ->pluck('barangay_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->all();

            $ids = array_values(array_unique(array_merge($ids, $legacyIds)));
        }

        return $ids;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function publicDocumentsForBarangay(Barangay $barangay): Collection
    {
        $items = collect();

        if (Schema::hasTable('abyip')) {
            $query = Abyip::query()
                ->documents()
                ->where('barangay_id', $barangay->id);

            if (Schema::hasColumn('abyip', 'status')) {
                $query->where('status', Abyip::STATUS_APPROVED);
            }

            if (Schema::hasColumn('abyip', 'pdf_data')) {
                $query->whereNotNull('pdf_data')->where('pdf_data', '!=', '');
            }

            $documents = $query
                ->orderByDesc('fiscal_year')
                ->orderByDesc('id')
                ->get(['id', 'fiscal_year', 'document_title', 'source_type', 'status']);

            foreach ($documents as $document) {

                $year = (int) ($document->fiscal_year ?: 0);
                $title = trim((string) ($document->document_title ?: ''));

                $items->push([
                    'id' => 'abyip-'.$document->id,
                    'source' => 'abyip',
                    'document_id' => (int) $document->id,
                    'year' => $year,
                    'title' => $title !== '' ? $title : ('ABYIP CY '.$year),
                    'file_url' => route('baranggay_abyip.file', [$barangay->slug, $document->id]),
                    'has_pdf' => true,
                ]);
            }
        }

        if (Schema::hasTable('abyips') && Schema::hasColumn('abyips', 'source_pdf_path')) {
            $legacyRows = BarangayAbyip::query()
                ->where('barangay_id', $barangay->id)
                ->whereNotNull('source_pdf_path')
                ->where('source_pdf_path', '!=', '')
                ->orderByDesc('year')
                ->get(['id', 'year', 'source_pdf_path']);

            foreach ($legacyRows as $legacy) {
                $path = (string) $legacy->source_pdf_path;
                if ($path === '' || ! Storage::disk('local')->exists($path)) {
                    continue;
                }

                $year = (int) $legacy->year;
                $items->push([
                    'id' => 'legacy-'.$legacy->id,
                    'source' => 'legacy',
                    'document_id' => (int) $legacy->id,
                    'year' => $year,
                    'title' => 'ABYIP CY '.$year,
                    'file_url' => route('baranggay_abyip.legacy_file', [$barangay->slug, $legacy->id]),
                    'has_pdf' => true,
                ]);
            }
        }

        return $items->sortByDesc('year')->values();
    }

    /**
     * @return array{content: string, filename: string}|null
     */
    public function pdfBinary(Barangay $barangay, int $documentId): ?array
    {
        if (! Schema::hasTable('abyip')) {
            return null;
        }

        $query = Abyip::query()
            ->documents()
            ->where('id', $documentId)
            ->where('barangay_id', $barangay->id);

        if (Schema::hasColumn('abyip', 'status')) {
            $query->where('status', Abyip::STATUS_APPROVED);
        }

        $document = $query->first();
        if ($document === null || ! filled($document->pdf_data)) {
            return null;
        }

        $binary = $this->decodePdfBinary((string) $document->pdf_data);
        if ($binary === null) {
            return null;
        }

        $year = (int) ($document->fiscal_year ?: 0);
        $filename = Str::slug((string) ($document->document_title ?: 'abyip-'.$barangay->slug.'-'.$year)).'.pdf';

        return [
            'content' => $binary,
            'filename' => $filename,
        ];
    }

    /**
     * @return array{content: string, filename: string}|null
     */
    public function legacyPdfBinary(Barangay $barangay, int $legacyId): ?array
    {
        if (! Schema::hasTable('abyips')) {
            return null;
        }

        $legacy = BarangayAbyip::query()
            ->where('id', $legacyId)
            ->where('barangay_id', $barangay->id)
            ->first();

        if ($legacy === null || ! filled($legacy->source_pdf_path)) {
            return null;
        }

        $path = (string) $legacy->source_pdf_path;
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $content = Storage::disk('local')->get($path);
        if ($content === null || $content === '') {
            return null;
        }

        return [
            'content' => $content,
            'filename' => 'abyip-'.$barangay->slug.'-'.$legacy->year.'.pdf',
        ];
    }

    private function decodePdfBinary(string $rawPdfData): ?string
    {
        $raw = trim($rawPdfData);

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'data:')) {
            $commaPos = strpos($raw, ',');
            if ($commaPos === false) {
                return null;
            }

            $raw = substr($raw, $commaPos + 1);
        }

        $binary = base64_decode($raw, true);

        return $binary === false ? null : $binary;
    }
}
