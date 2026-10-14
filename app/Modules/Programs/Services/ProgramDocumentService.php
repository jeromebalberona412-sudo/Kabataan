<?php

namespace App\Modules\Programs\Services;

use App\Models\ProgramApplication;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Support\MailUrl;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProgramDocumentService
{
    public const MAX_FILE_SIZE_KB = 5120;

    private const DISK = 'public';

    /** @var list<string> */
    private const IMAGE_MIMES = ['image/jpeg', 'image/jpg', 'image/png'];

    public function __construct(
        private readonly ProgramIdReuseService $idReuseService,
        private readonly CloudinaryService $cloudinary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function uploadDraft(
        User $user,
        int $scheduleProgramId,
        string $questionId,
        UploadedFile $file,
        string $fileType = 'pdf',
        int $maxSizeKb = self::MAX_FILE_SIZE_KB,
    ): array {
        $this->assertValidUpload($file, $fileType, $maxSizeKb);

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $extension = strtolower($file->getClientOriginalExtension());
        $isImage = in_array($mime, self::IMAGE_MIMES, true) || in_array($extension, ['jpg', 'jpeg', 'png'], true);
        if ($isImage) {
            return $this->storeCloudinaryImageDraft($user, $scheduleProgramId, $questionId, $file, $mime !== '' ? $mime : $this->mimeFromPath($extension));
        }

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
            'mime' => $file->getMimeType() ?: $this->mimeFromPath($path),
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
                if (is_array($meta) && (($meta['storage'] ?? '') === 'cloudinary') && ! empty($meta['url'])) {
                    $documents[(string) $questionId] = $this->formatCloudinaryDocumentMeta(
                        $scheduleProgramId,
                        (string) $questionId,
                        $meta,
                    );

                    continue;
                }

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
            if (! is_array($meta)) {
                continue;
            }

            $qid = (string) $questionId;
            if ($this->idReuseService->isReuseMeta($meta)) {
                $owner = $application->kabataan_id
                    ? User::query()->find($application->kabataan_id)
                    : null;
                if ($owner !== null) {
                    $documents[$qid] = $this->idReuseService->enrichReuseMeta(
                        $owner,
                        $meta,
                        (int) $application->program_id,
                        $qid,
                    );
                }

                continue;
            }

            if (empty($meta['path'])) {
                continue;
            }

            $documents[$qid] = $this->enrichDocumentUrls($meta, $application->id);
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
            if ($this->idReuseService->answerRequestsReuse($fileMeta)) {
                continue;
            }

            if (is_array($fileMeta) && ($fileMeta['storage'] ?? '') === 'cloudinary' && ! empty($fileMeta['url'])) {
                $documents[$questionId] = [
                    'question_id' => $questionId,
                    'question_label' => $answer['question_label'] ?? null,
                    'storage' => 'cloudinary',
                    'public_id' => $fileMeta['public_id'] ?? null,
                    'url' => $fileMeta['url'],
                    'path' => $fileMeta['public_id'] ?? $fileMeta['url'],
                    'original_name' => $fileMeta['original_name'] ?? 'image',
                    'size' => (int) ($fileMeta['size'] ?? 0),
                    'mime' => $fileMeta['mime'] ?? 'image/jpeg',
                    'uploaded_at' => $fileMeta['uploaded_at'] ?? now()->toIso8601String(),
                ];

                continue;
            }

            $draftPath = is_array($fileMeta) && is_string($fileMeta['path'] ?? null)
                ? $this->normalizeStoragePath($fileMeta['path'])
                : null;

            if ($draftPath === null || ! Storage::disk(self::DISK)->exists($draftPath)) {
                $draftPath = $this->findDraftPath($user->id, $application->program_id, $questionId);
            }

            if ($draftPath === null || ! Storage::disk(self::DISK)->exists($draftPath)) {
                throw ValidationException::withMessages([
                    "answers.{$questionId}" => ['Please upload the required document.'],
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
                'mime' => $this->mimeFromPath($permanentPath),
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
    ): Response {
        $application = ProgramApplication::query()
            ->where('kabataan_id', $user->id)
            ->where('program_id', $scheduleProgramId)
            ->whereNot('status', ProgramApplication::STATUS_CANCELLED)
            ->latest('id')
            ->first();

        $path = null;
        $originalName = 'document.pdf';

        $meta = null;

        if ($application !== null) {
            $meta = ($application->required_documents ?? [])[$questionId] ?? null;
            if ($this->idReuseService->isReuseMeta($meta)) {
                $side = request()->query('side', 'front');

                return $this->idReuseService->streamReuseMeta(
                    $user,
                    is_array($meta) ? $meta : [],
                    is_string($side) ? $side : 'front',
                    $download,
                );
            }
            if (is_array($meta) && ($meta['storage'] ?? '') === 'cloudinary' && ! empty($meta['url'])) {
                return redirect()->away((string) $meta['url']);
            }

            if (is_array($meta) && ! empty($meta['path'])) {
                $path = $meta['path'];
                $originalName = $meta['original_name'] ?? basename($path);
            }
        }

        $draftMeta = $this->readDraftMeta($user->id, $scheduleProgramId, $questionId);
        if (is_array($draftMeta) && ($draftMeta['storage'] ?? '') === 'cloudinary' && ! empty($draftMeta['url'])) {
            return redirect()->away((string) $draftMeta['url']);
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
        $mime = is_array($meta) ? ($meta['mime'] ?? null) : null;

        return Storage::disk(self::DISK)->response($path, $originalName, [
            'Content-Type' => $mime ?: $this->mimeFromPath($path),
            'Content-Disposition' => $disposition.'; filename="'.addslashes($originalName).'"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeCloudinaryImageDraft(
        User $user,
        int $scheduleProgramId,
        string $questionId,
        UploadedFile $file,
        string $mime,
    ): array {
        if (! $this->cloudinary->isConfigured()) {
            throw ValidationException::withMessages([
                'file' => ['Image upload is not available right now. Please try again later.'],
            ]);
        }

        try {
            $uploaded = $this->cloudinary->upload($file, 'program_'.$scheduleProgramId.'_'.$questionId);
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'file' => ['The image could not be uploaded. Please try again.'],
            ]);
        }

        $safeQuestionId = $this->sanitizeQuestionId($questionId);
        $directory = $this->draftDirectory($user->id, $scheduleProgramId);
        Storage::disk(self::DISK)->makeDirectory($directory);

        $meta = [
            'question_id' => $questionId,
            'storage' => 'cloudinary',
            'public_id' => $uploaded['public_id'],
            'url' => $uploaded['url'],
            'path' => $uploaded['public_id'],
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $mime,
            'uploaded_at' => now()->toIso8601String(),
        ];

        Storage::disk(self::DISK)->put(
            $directory.'/'.$safeQuestionId.'.meta.json',
            json_encode($meta, JSON_THROW_ON_ERROR),
        );

        return $this->formatCloudinaryDocumentMeta($scheduleProgramId, $questionId, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function formatCloudinaryDocumentMeta(int $scheduleProgramId, string $questionId, array $meta): array
    {
        $formatted = $this->formatDocumentMeta(
            $scheduleProgramId,
            $questionId,
            (string) ($meta['original_name'] ?? 'image'),
            (string) ($meta['original_name'] ?? 'image'),
            (int) ($meta['size'] ?? 0),
            (string) ($meta['uploaded_at'] ?? now()->toIso8601String()),
        );
        $formatted['storage'] = 'cloudinary';
        $formatted['public_id'] = $meta['public_id'] ?? null;
        $formatted['url'] = $meta['url'] ?? null;
        $formatted['path'] = $meta['public_id'] ?? ($meta['url'] ?? '');
        $formatted['mime'] = $meta['mime'] ?? 'image/jpeg';

        return $formatted;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readDraftMeta(int $userId, int $scheduleProgramId, string $questionId): ?array
    {
        $metaPath = $this->draftDirectory($userId, $scheduleProgramId).'/'.$this->sanitizeQuestionId($questionId).'.meta.json';
        if (! Storage::disk(self::DISK)->exists($metaPath)) {
            return null;
        }

        $meta = json_decode((string) Storage::disk(self::DISK)->get($metaPath), true);

        return is_array($meta) ? $meta : null;
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
            'mime' => $this->mimeFromPath($path),
            'uploaded_at' => $uploadedAt,
            'uploaded_at_display' => $this->formatTimestamp($uploadedAt),
            'preview_url' => MailUrl::sameOrigin(route('kabataan.programs.documents.show', [
                'scheduleProgramId' => $scheduleProgramId,
                'questionId' => $questionId,
            ])),
            'download_url' => MailUrl::sameOrigin(route('kabataan.programs.documents.show', [
                'scheduleProgramId' => $scheduleProgramId,
                'questionId' => $questionId,
                'download' => 1,
            ])),
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

    public function assertValidUpload(UploadedFile $file, string $fileType = 'pdf', int $maxSizeKb = self::MAX_FILE_SIZE_KB): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file is invalid.'],
            ]);
        }

        $maxSizeKb = $maxSizeKb > 0 ? $maxSizeKb : self::MAX_FILE_SIZE_KB;
        if ($file->getSize() > $maxSizeKb * 1024) {
            $maxMb = max(1, (int) ceil($maxSizeKb / 1024));
            throw ValidationException::withMessages([
                'file' => ['File must not exceed '.$maxMb.' MB.'],
            ]);
        }

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getClientOriginalExtension());
        $isPdf = $extension === 'pdf' || $mime === 'application/pdf';
        $isImage = in_array($mime, self::IMAGE_MIMES, true) || in_array($extension, ['jpg', 'jpeg', 'png'], true);

        $fileType = in_array($fileType, ['pdf', 'image', 'pdf_or_image'], true) ? $fileType : 'pdf';

        if ($fileType === 'pdf' && ! $isPdf) {
            throw ValidationException::withMessages([
                'file' => ['Only PDF files are allowed.'],
            ]);
        }

        if ($fileType === 'image' && ! $isImage) {
            throw ValidationException::withMessages([
                'file' => ['Only JPG and PNG images are allowed.'],
            ]);
        }

        if ($fileType === 'pdf_or_image' && ! $isPdf && ! $isImage) {
            throw ValidationException::withMessages([
                'file' => ['Only PDF, JPG, or PNG files are allowed.'],
            ]);
        }
    }

    private function mimeFromPath(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
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
            return Carbon::parse($timestamp)->format('M j, Y g:i A');
        } catch (\Throwable) {
            return $timestamp;
        }
    }
}
