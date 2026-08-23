<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KabataanPhotoService
{
    public const DISK = 'public';

    public const DIRECTORY = 'kabataan_photos';

    /**
     * Save a verified selfie from base64 data URL or uploaded file.
     *
     * @return array{path: string, url: string}
     */
    public function storeVerifiedSelfie(string|UploadedFile $source, ?string $email = null): array
    {
        $binary = $this->resolveBinary($source);
        $this->assertValidImageBinary($binary);

        $filename = $this->buildFilename($email);
        $relativePath = self::DIRECTORY . '/' . $filename;

        Storage::disk(self::DISK)->put($relativePath, $binary);

        return [
            'path' => $relativePath,
            'url'  => Storage::disk(self::DISK)->url($relativePath),
        ];
    }

    public function deleteByPath(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        if (Storage::disk(self::DISK)->exists($relativePath)) {
            Storage::disk(self::DISK)->delete($relativePath);
        }
    }

    public function publicUrl(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        return Storage::disk(self::DISK)->url($relativePath);
    }

    private function resolveBinary(string|UploadedFile $source): string
    {
        if ($source instanceof UploadedFile) {
            $binary = @file_get_contents($source->getRealPath());

            if ($binary === false) {
                throw ValidationException::withMessages([
                    'verified_selfie' => ['Unable to read the verified selfie. Please retake verification.'],
                ]);
            }

            return $binary;
        }

        $dataUrl = trim($source);

        if (! str_starts_with($dataUrl, 'data:image/')) {
            throw ValidationException::withMessages([
                'verified_selfie' => ['Invalid verified selfie format. Please complete identity verification again.'],
            ]);
        }

        if (! preg_match('#^data:image/(jpeg|jpg|png|webp);base64,(.+)$#i', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'verified_selfie' => ['Verified selfie must be a JPEG, PNG, or WEBP image.'],
            ]);
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false) {
            throw ValidationException::withMessages([
                'verified_selfie' => ['Unable to decode verified selfie. Please retake verification.'],
            ]);
        }

        return $binary;
    }

    private function assertValidImageBinary(string $binary): void
    {
        if (strlen($binary) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'verified_selfie' => ['Verified selfie must be 5MB or smaller.'],
            ]);
        }

        if (@getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages([
                'verified_selfie' => ['Verified selfie must be a valid image file.'],
            ]);
        }
    }

    private function buildFilename(?string $email): string
    {
        $slug = $email
            ? Str::slug(Str::before($email, '@'), '_')
            : 'kabataan';

        return sprintf(
            '%s_%s_%s.jpg',
            $slug ?: 'kabataan',
            now()->format('YmdHis'),
            Str::lower(Str::random(8))
        );
    }

    public function ensureDirectoryExists(): void
    {
        if (! Storage::disk(self::DISK)->exists(self::DIRECTORY)) {
            Storage::disk(self::DISK)->makeDirectory(self::DIRECTORY);
        }
    }

    /**
     * @return array{path: string, url: string}
     */
    public function storeVerifiedSelfieFromBinary(string $binary, ?string $email = null): array
    {
        $this->assertValidImageBinary($binary);

        $filename = $this->buildFilename($email);
        $relativePath = self::DIRECTORY . '/' . $filename;

        Storage::disk(self::DISK)->put($relativePath, $binary);

        return [
            'path' => $relativePath,
            'url'  => Storage::disk(self::DISK)->url($relativePath),
        ];
    }

    public function resolveBinaryForDraft(string $source): string
    {
        return $this->resolveBinary($source);
    }

    public function assertValidImageBinaryForDraft(string $binary): void
    {
        $this->assertValidImageBinary($binary);
    }
}
