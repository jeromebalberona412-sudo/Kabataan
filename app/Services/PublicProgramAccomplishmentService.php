<?php

namespace App\Services;

use App\Models\ProgramAccomplishmentDocument;
use App\Models\ProgramAccomplishmentReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicProgramAccomplishmentService
{
    /**
     * @return Collection<int, ProgramAccomplishmentReport>
     */
    public function publishedForBarangay(int $barangayId): Collection
    {
        if (! Schema::hasTable('programs_accomplishment_reports')) {
            return collect();
        }

        $relations = ['program'];

        if (Schema::hasTable('programs_accomplishment')) {
            $relations[] = 'images';
        }

        if (Schema::hasTable('programs_accomplishment_documents')) {
            $relations[] = 'documents';
        }

        return ProgramAccomplishmentReport::query()
            ->publiclyVisible()
            ->where('barangay_id', $barangayId)
            ->with($relations)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function barangayIdsWithPublishedReports(): array
    {
        if (! Schema::hasTable('programs_accomplishment_reports')) {
            return [];
        }

        return ProgramAccomplishmentReport::query()
            ->publiclyVisible()
            ->distinct()
            ->pluck('barangay_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function categoryForReport(ProgramAccomplishmentReport $report): string
    {
        $committee = trim((string) ($report->program?->committee ?? ''));
        if ($committee !== '') {
            return $committee;
        }

        $type = trim((string) ($report->program?->program_type ?? ''));
        if ($type !== '' && strcasecmp($type, 'other') !== 0) {
            return ucwords(str_replace(['-', '_'], ' ', $type));
        }

        $letter = strtoupper(trim((string) ($report->program?->program_letter ?? '')));

        return match ($letter) {
            'A' => 'Education',
            'B' => 'Environment',
            'C' => 'Disaster',
            'D' => 'Livelihood',
            'E' => 'Health',
            'F' => 'Anti-Drug',
            'G' => 'Gender',
            'H' => 'Feeding',
            'I' => 'Sports',
            default => 'Other',
        };
    }

    public function durationLabel($start, $end): string
    {
        if ($start === null || $end === null) {
            return '—';
        }

        $months = max(0, $start->diffInMonths($end));
        if ($months < 1) {
            $days = max(1, $start->diffInDays($end));

            return $days === 1 ? '1 Day' : $days.' Days';
        }

        if ($months < 12) {
            return $months === 1 ? '1 Month' : $months.' Months';
        }

        $years = max(1, (int) round($months / 12));

        return $years === 1 ? '1 Year' : $years.' Years';
    }

    public function publishedByIdForBarangay(int $barangayId, int $reportId): ?ProgramAccomplishmentReport
    {
        if (! Schema::hasTable('programs_accomplishment_reports')) {
            return null;
        }

        $relations = ['program'];

        if (Schema::hasTable('programs_accomplishment')) {
            $relations[] = 'images';
        }

        if (Schema::hasTable('programs_accomplishment_documents')) {
            $relations[] = 'documents';
        }

        return ProgramAccomplishmentReport::query()
            ->publiclyVisible()
            ->where('barangay_id', $barangayId)
            ->where('id', $reportId)
            ->with($relations)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicCard(ProgramAccomplishmentReport $report, string $barangayName, string $barangaySlug = ''): array
    {
        $images = $report->relationLoaded('images') ? $report->images : collect();
        $cover = $images->first();
        $start = $report->program?->start_date;
        $end = $report->program?->end_date ?? $report->actual_completion_date;
        $displayDate = $report->actual_completion_date
            ?: $report->published_at
            ?: $report->program?->end_date;
        $year = $displayDate?->year
            ?? $report->program?->end_date?->year
            ?? (int) date('Y');

        $photos = $images->map(function ($image) {
            $src = $this->publicMediaUrl($image->secure_url ?: $image->image_url);

            return [
                'src' => $src,
                'caption' => (string) ($image->caption ?? ''),
            ];
        })->filter(fn ($photo) => filled($photo['src']))->values();

        $docs = ($report->relationLoaded('documents') ? $report->documents : collect())
            ->filter(fn ($document) => $this->isPublicDocument($document) && filled($document->stored_path))
            ->map(function ($document) use ($barangaySlug) {
                $name = (string) $document->original_name;
                $ext = strtoupper((string) pathinfo($name, PATHINFO_EXTENSION));
                $url = $barangaySlug !== ''
                    ? route('program_accomplishments.barangays.document', [
                        'barangay' => $barangaySlug,
                        'document' => $document->id,
                    ])
                    : $this->officialsPublicFileUrl((string) $document->stored_path);

                return [
                    'id' => (int) $document->id,
                    'name' => $name !== '' ? $name : 'MS Word report',
                    'url' => $url,
                    'type' => $ext !== '' ? $ext : 'DOC',
                    'size' => $this->formatFileSize((int) ($document->file_size ?? 0)),
                ];
            })
            ->filter(fn ($document) => filled($document['url']))
            ->values();

        $coverUrl = $cover ? $this->publicMediaUrl($cover->secure_url ?: $cover->image_url) : null;
        $startLabel = $report->actual_implementation_date?->format('M d, Y')
            ?? $start?->format('M d, Y')
            ?? '—';
        $endLabel = $report->actual_completion_date?->format('M d, Y')
            ?? $end?->format('M d, Y')
            ?? '—';

        return [
            'id' => $report->id,
            'title' => $report->program?->program_name ?? $report->title,
            'description' => $report->description,
            'category' => $this->categoryForReport($report),
            'status' => 'Completed',
            'date_label' => $displayDate?->format('M d, Y') ?? '—',
            'start_label' => $startLabel,
            'end_label' => $endLabel,
            'published_label' => $report->published_at?->format('M d, Y'),
            'implementation_label' => $report->actual_implementation_date?->format('M d, Y'),
            'sort_date' => optional($displayDate)->toDateString() ?? '',
            'year' => (string) $year,
            'location' => $barangayName,
            'duration' => $this->durationLabel($start, $end),
            'committee' => $report->program?->committee ?: $this->categoryForReport($report),
            'beneficiaries' => (int) ($report->participants_count ?? 0),
            'target_beneficiaries' => (int) ($report->target_beneficiaries ?? 0),
            'expenditure' => (float) $report->actual_expense,
            'approved_budget' => $report->plannedBudget(),
            'remaining' => $report->remainingBudget(),
            'objectives' => $report->objectives,
            'summary' => $report->implementation_summary,
            'actual_result' => $report->actual_result,
            'cover' => $coverUrl,
            'photos' => $photos,
            'documents' => $docs,
        ];
    }

    public function downloadPublicDocument(int $barangayId, int $documentId): BinaryFileResponse|StreamedResponse|null
    {
        if (! Schema::hasTable('programs_accomplishment_documents')) {
            return null;
        }

        $document = ProgramAccomplishmentDocument::query()
            ->with('accomplishmentReport')
            ->find($documentId);

        $report = $document?->accomplishmentReport;
        if (
            $document === null
            || $report === null
            || (int) $report->barangay_id !== $barangayId
            || ! $this->isPublicDocument($document)
            || ! in_array((string) $report->status, [ProgramAccomplishmentReport::STATUS_PUBLISHED, 'Submitted'], true)
        ) {
            return null;
        }

        $storedPath = ltrim(str_replace('\\', '/', (string) $document->stored_path), '/');
        if (! $this->isSafeStoredPath($storedPath)) {
            return null;
        }

        $downloadName = $this->safeDownloadName((string) $document->original_name);
        $mime = $this->documentMimeType((string) $document->mime_type, $downloadName);
        $localPath = $this->resolveLocalDocumentPath($storedPath);

        if ($localPath !== null) {
            return response()->download($localPath, $downloadName, [
                'Content-Type' => $mime,
            ]);
        }

        $remoteUrl = $this->officialsPublicFileUrl($storedPath);
        if ($remoteUrl === null) {
            return null;
        }

        $remote = Http::timeout(45)->withHeaders(['Accept' => '*/*'])->get($remoteUrl);
        if (! $remote->successful() || $remote->body() === '') {
            return null;
        }

        $body = $remote->body();

        return response()->streamDownload(function () use ($body) {
            echo $body;
        }, $downloadName, [
            'Content-Type' => $mime,
        ]);
    }

    private function isPublicDocument(object $document): bool
    {
        $visibility = strtolower(trim((string) ($document->visibility ?? '')));

        return $visibility === '' || $visibility === 'public';
    }

    private function publicMediaUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return $this->officialsPublicFileUrl($url);
    }

    private function officialsPublicFileUrl(string $storedPath): ?string
    {
        $path = ltrim(str_replace('\\', '/', $storedPath), '/');
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim($this->officialsAppUrl(), '/');
        if ($base === '') {
            return asset('storage/'.$path);
        }

        return $base.'/storage/'.$path;
    }

    private function officialsAppUrl(): string
    {
        $configured = rtrim((string) config('services.sk_officials_app_url', ''), '/');
        $requestHost = request()->getHost();
        $isLoopback = $configured === ''
            || str_contains($configured, 'localhost')
            || str_contains($configured, '127.0.0.1');

        if ($isLoopback && $requestHost !== '' && ! in_array($requestHost, ['localhost', '127.0.0.1'], true)) {
            $scheme = request()->getScheme() ?: 'http';
            $port = parse_url($configured, PHP_URL_PORT) ?: 8000;

            return $scheme.'://'.$requestHost.':'.$port;
        }

        return $configured;
    }

    private function isSafeStoredPath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, '..')
            && str_starts_with($path, 'program_accomplishment_docs/');
    }

    private function resolveLocalDocumentPath(string $storedPath): ?string
    {
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedPath);
        $roots = [
            storage_path('app/public'),
            public_path('storage'),
            dirname(base_path()).DIRECTORY_SEPARATOR.'SK_Officials'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
            dirname(base_path()).DIRECTORY_SEPARATOR.'SK_Officials'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'storage',
        ];

        foreach ($roots as $root) {
            $candidate = $root.DIRECTORY_SEPARATOR.$relative;
            if (! is_file($candidate)) {
                continue;
            }

            $realFile = realpath($candidate);
            $realRoot = realpath($root);
            if ($realFile === false || $realRoot === false) {
                continue;
            }

            if (str_starts_with($realFile, $realRoot.DIRECTORY_SEPARATOR)) {
                return $realFile;
            }
        }

        return null;
    }

    private function safeDownloadName(string $originalName): string
    {
        $name = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $originalName));
        $name = preg_replace('/[^\w.\- ()]+/u', '_', $name) ?: '';

        if ($name === '' || $name === '.' || $name === '..') {
            return 'program-accomplishment.docx';
        }

        return $name;
    }

    private function documentMimeType(string $storedMime, string $downloadName): string
    {
        $mime = trim($storedMime);
        if ($mime !== '') {
            return $mime;
        }

        $ext = strtolower((string) pathinfo($downloadName, PATHINFO_EXTENSION));

        return match ($ext) {
            'doc' => 'application/msword',
            'pdf' => 'application/pdf',
            default => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}
