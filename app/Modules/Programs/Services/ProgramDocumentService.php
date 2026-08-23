<?php

namespace App\Modules\Programs\Services;

use App\Models\ProgramApplication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramDocumentService
{
    public const MAX_FILE_SIZE_KB = 5120;

    private const DISK = 'public';

    /**
     * @return array<string, mixed>
     */
    public function uploadDraft(User $user, int $scheduleProgramId, string $questionId, UploadedFile $file): array
    {
        $this->assertValidPdf($file);

        $safeQuestionId = $this->sanitizeQuestionId($questionId);
        $directory = $this->draftDirectory($user->id, $scheduleProgramId);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $storedName = $safeQuestionId.'_'.Str::uuid().'.'.$extension;

        Storage::disk(self::DISK)->makeDirectory($directory);

        $oldFiles = Storage::disk(self::DISK)->files($directory);
        foreach ($oldFiles as $oldFile) {
            if (str_starts_with(basename($oldFile), $safeQuestionId.'_')) {
                Storage::disk(self::DISK)->delete($oldFile);
            }
        }

        $path = $file->storeAs($directory, $storedName, self::DISK);

        Storage::disk(self::DISK)->put($directory.'/'.$safeQuestionId.'.meta.json', json_encode([
            'question_id' => $questionId,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => 'application/pdf',
            'uploaded_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        return $this->formatDocumentMeta(
            $scheduleProgramId,
            $questionId,
            $path,
            $file->getClientOriginalName(),
            $file->getSize(),
            now()->toIso8601String(),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function listDraftDocuments(User $user, int $scheduleProgramId, array $questionIds): array
    {
        $documents = [];
        $directory = $this->draftDirectory($user->id, $scheduleProgramId);

        if (! Storage::disk(self::DISK)->exists($directory)) {
            return $documents;
        }

        foreach ($questionIds as $questionId) {
            $safeQuestionId = $this->sanitizeQuestionId((string) $questionId);
            $metaPath = $directory.'/'.$safeQuestionId.'.meta.json';

            if (Storage::disk(self::DISK)->exists($metaPath)) {
                $meta = json_decode((string) Storage::disk(self::DISK)->get($metaPath), true);
                if (is_array($meta) && ! empty($meta['path']) && Storage::disk(self::DISK)->exists($meta['path'])) {
                    $documents[(string) $questionId] = $this->formatDocumentMeta(
                        $scheduleProgramId,
                        (string) $questionId,
                        $meta['path'],
                        (string) ($meta['original_name'] ?? basename($meta['path'])),
                        (int) ($meta['size'] ?? Storage::disk(self::DISK)->size($meta['path'])),
                        (string) ($meta['uploaded_at'] ?? date('c', Storage::disk(self::DISK)->lastModified($meta['path']))),
                    );

                    continue;
                }
            }

            $match = collect(Storage::disk(self::DISK)->files($directory))
                ->first(fn (string $file) => str_starts_with(basename($file), $safeQuestionId.'_'));

            if ($match === null) {
                continue;
            }

            $documents[(string) $questionId] = $this->formatDocumentMeta(
                $scheduleProgramId,
                (string) $questionId,
                $match,
                basename($match),
                Storage::disk(self::DISK)->size($match),
                date('c', Storage::disk(self::DISK)->lastModified($match)),
            );
        }

        return $documents;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function listApplicationDocuments(ProgramApplication $application): array
    {
        $documents = [];

        foreach ($application->required_documents ?? [] as $questionId => $meta) {
            if (! is_array($meta) || empty($meta['path'])) {
                continue;
            }

            $documents[(string) $questionId] = $this->enrichDocumentUrls($meta, $application->id);
        }

        return $documents;
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @return array<string, array<string, mixed>>
     */
    public function finalizeDraftDocuments(
        User $user,
        ProgramApplication $application,
        array $answers,
    ): array {
        $documents = [];
        $draftDirectory = $this->draftDirectory($user->id, $application->program_id);
        $permanentDirectory = $this->applicationDirectory($application->id);

        Storage::disk(self::DISK)->makeDirectory($permanentDirectory);

        foreach ($answers as $answer) {
            if (($answer['question_type'] ?? '') !== 'file') {
                continue;
            }

            $questionId = (string) ($answer['question_id'] ?? '');
            if ($questionId === '') {
                continue;
            }

            $fileMeta = $this->resolveFileAnswerMeta($answer['answer'] ?? null);
            $draftPath = is_string($fileMeta['path'] ?? null) ? $this->normalizeStoragePath($fileMeta['path']) : null;

            if ($draftPath === null || ! Storage::disk(self::DISK)->exists($draftPath)) {
                $draftPath = $this->findDraftPath($user->id, $application->program_id, $questionId);
            }

            if ($draftPath === null || ! Storage::disk(self::DISK)->exists($draftPath)) {
                throw ValidationException::withMessages([
                    "answers.{$questionId}" => ['Please upload the required PDF document.'],
                ]);
            }

            $extension = pathinfo($draftPath, PATHINFO_EXTENSION) ?: 'pdf';
            $permanentPath = $permanentDirectory.'/'.$this->sanitizeQuestionId($questionId).'.'.$extension;

            if ($draftPath !== $permanentPath) {
                Storage::disk(self::DISK)->copy($draftPath, $permanentPath);
            }

            $documents[$questionId] = [
                'question_id' => $questionId,
                'question_label' => $answer['question_label'] ?? null,
                'disk' => self::DISK,
                'path' => $permanentPath,
                'original_name' => $fileMeta['original_name'] ?? basename($draftPath),
                'size' => Storage::disk(self::DISK)->size($permanentPath),
                'mime' => 'application/pdf',
                'uploaded_at' => $fileMeta['uploaded_at'] ?? now()->toIso8601String(),
            ];
        }

        return $documents;
    }

    public function resolveDocumentForUser(
        User $user,
        int $scheduleProgramId,
        string $questionId,
        bool $download = false,
    ): StreamedResponse {
        $application = ProgramApplication::query()
            ->where('kabataan_id', $user->id)
            ->where('program_id', $scheduleProgramId)
            ->whereNot('status', ProgramApplication::STATUS_CANCELLED)
            ->latest('id')
            ->first();

        $path = null;
        $originalName = 'document.pdf';

        if ($application !== null) {
            $meta = ($application->required_documents ?? [])[$questionId] ?? null;
            if (is_array($meta) && ! empty($meta['path'])) {
                $path = $meta['path'];
                $originalName = $meta['original_name'] ?? basename($path);
            }
        }

        if ($path === null) {
            $path = $this->findDraftPath($user->id, $scheduleProgramId, $questionId);
            $originalName = $path ? basename($path) : $originalName;
        }

        if ($path === null || ! Storage::disk(self::DISK)->exists($path)) {
            abort(404, 'Document not found.');
        }

        $this->assertUserOwnsDocument($user, $path);

        $disposition = $download ? 'attachment' : 'inline';

        return Storage::disk(self::DISK)->response($path, $originalName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($originalName).'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDocumentMeta(
        int $scheduleProgramId,
        string $questionId,
        string $path,
        string $originalName,
        int $size,
        string $uploadedAt,
    ): array {
        return [
            'question_id' => $questionId,
            'path' => $path,
            'original_name' => $originalName,
            'size' => $size,
            'size_display' => $this->formatFileSize($size),
            'mime' => 'application/pdf',
            'uploaded_at' => $uploadedAt,
            'uploaded_at_display' => $this->formatTimestamp($uploadedAt),
            'preview_url' => route('kabataan.programs.documents.show', [
                'scheduleProgramId' => $scheduleProgramId,
                'questionId' => $questionId,
            ]),
            'download_url' => route('kabataan.programs.documents.show', [
                'scheduleProgramId' => $scheduleProgramId,
                'questionId' => $questionId,
                'download' => 1,
            ]),
            'status' => 'uploaded',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function enrichDocumentUrls(array $meta, int $applicationId): array
    {
        $questionId = (string) ($meta['question_id'] ?? '');
        $scheduleProgramId = ProgramApplication::query()->whereKey($applicationId)->value('program_id');

        $meta['size_display'] = $this->formatFileSize((int) ($meta['size'] ?? 0));
        $meta['uploaded_at_display'] = $this->formatTimestamp((string) ($meta['uploaded_at'] ?? ''));
        $meta['preview_url'] = route('kabataan.programs.documents.show', [
            'scheduleProgramId' => $scheduleProgramId,
            'questionId' => $questionId,
        ]);
        $meta['download_url'] = route('kabataan.programs.documents.show', [
            'scheduleProgramId' => $scheduleProgramId,
            'questionId' => $questionId,
            'download' => 1,
        ]);
        $meta['status'] = 'uploaded';

        return $meta;
    }

    private function assertValidPdf(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file is invalid.'],
            ]);
        }

        $mime = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension !== 'pdf' && $mime !== 'application/pdf') {
            throw ValidationException::withMessages([
                'file' => ['Only PDF files are allowed.'],
            ]);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_KB * 1024) {
            throw ValidationException::withMessages([
                'file' => ['PDF file must not exceed 5 MB.'],
            ]);
        }
    }

    private function assertUserOwnsDocument(User $user, string $path): void
    {
        $path = $this->normalizeStoragePath($path);

        $allowedPrefixes = [
            'program-applications/drafts/'.$user->id.'/',
            'program-applications/',
        ];

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            abort(403, 'Unauthorized access to document.');
        }

        if (str_starts_with($path, 'program-applications/') && ! str_contains($path, '/drafts/'.$user->id.'/')) {
            $applicationId = $this->applicationIdFromPath($path);
            if ($applicationId === null) {
                abort(403, 'Unauthorized access to document.');
            }

            $ownsApplication = ProgramApplication::query()
                ->whereKey($applicationId)
                ->where('kabataan_id', $user->id)
                ->exists();

            if (! $ownsApplication) {
                abort(403, 'Unauthorized access to document.');
            }
        }
    }

    private function draftDirectory(int $userId, int $scheduleProgramId): string
    {
        return 'program-applications/drafts/'.$userId.'/'.$scheduleProgramId;
    }

    private function applicationDirectory(int $applicationId): string
    {
        return 'program-applications/'.$applicationId;
    }

    private function findDraftPath(int $userId, int $scheduleProgramId, string $questionId): ?string
    {
        $directory = $this->draftDirectory($userId, $scheduleProgramId);
        $safeQuestionId = $this->sanitizeQuestionId($questionId);

        if (! Storage::disk(self::DISK)->exists($directory)) {
            return null;
        }

        return collect(Storage::disk(self::DISK)->files($directory))
            ->first(fn (string $file) => str_starts_with(basename($file), $safeQuestionId.'_'));
    }

    private function sanitizeQuestionId(string $questionId): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $questionId) ?: 'document';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFileAnswerMeta(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function normalizeStoragePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function applicationIdFromPath(string $path): ?int
    {
        if (preg_match('#^program-applications/(\d+)/#', $path, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 Bytes';
        }

        $units = ['Bytes', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }

    private function formatTimestamp(string $timestamp): string
    {
        if ($timestamp === '') {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($timestamp)->format('M j, Y g:i A');
        } catch (\Throwable) {
            return $timestamp;
        }
    }
}
