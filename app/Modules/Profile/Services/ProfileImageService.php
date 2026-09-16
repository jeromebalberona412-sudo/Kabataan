<?php

namespace App\Modules\Profile\Services;

use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileImageService
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ];

    public function __construct(private readonly CloudinaryService $cloudinary) {}

    public function defaultAvatarUrl(User $user, ?string $displayName = null): string
    {
        $name = $displayName ?: ($user->name ?: 'Youth User');

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&size=150&background=0039A8&color=fff';
    }

    public function resolveDisplayUrl(User $user, ?string $displayName = null): string
    {
        $raw = trim((string) ($user->profile_image_url ?? ''));
        $publicId = trim((string) ($user->profile_image_public_id ?? ''));
        $isLocalPublicId = $publicId !== '' && str_starts_with($publicId, 'profile-images/');

        // Prefer Cloudinary delivery when we have a cloud public_id (fixes old
        // silent local-fallback rows that never displayed in production).
        if (
            $publicId !== ''
            && ! $isLocalPublicId
            && $this->cloudinary->isConfigured()
        ) {
            try {
                return CloudinaryService::cacheBust(
                    $this->cloudinary->deliverUrl(
                        $publicId,
                        $raw !== '' ? $this->cloudinary->extractVersionFromUrl($raw) : null
                    ),
                    $user->profile_image_uploaded_at
                );
            } catch (\Throwable) {
                // Fall through to stored URL / default avatar.
            }
        }

        if ($raw !== '') {
            $url = $this->normalizeStoredProfileImageUrl($raw);

            if ($url !== '' && $this->looksLikeImageUrl($url)) {
                return CloudinaryService::cacheBust(
                    $url,
                    $user->profile_image_uploaded_at
                );
            }
        }

        return $this->defaultAvatarUrl($user, $displayName);
    }

    private function looksLikeImageUrl(string $url): bool
    {
        if (str_starts_with($url, '/storage/')) {
            return true;
        }

        return (bool) preg_match('#^https?://#i', $url);
    }

    private function normalizeStoredProfileImageUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        if (preg_match('#/storage/(.+)$#', $url, $matches)) {
            return '/storage/'.$matches[1];
        }

        if (str_starts_with($url, 'storage/')) {
            return '/'.$url;
        }

        if (str_starts_with($url, 'profile-images/')) {
            return '/storage/'.$url;
        }

        if (str_starts_with($url, '/storage/')) {
            return $url;
        }

        // Absolute Cloudinary URLs: keep if already optimized; otherwise rebuild.
        if (str_contains($url, 'res.cloudinary.com')) {
            $normalized = $this->cloudinary->normalizeUrl($url);

            return $normalized ?: $url;
        }

        $normalized = $this->cloudinary->normalizeUrl($url);

        return $normalized ?: $url;
    }

    public function canChangeProfileImage(User $user): bool
    {
        if (! $user->profile_image_uploaded_at) {
            return true;
        }

        if (! $user->profile_image_change_available_at) {
            return true;
        }

        return now()->gte($user->profile_image_change_available_at);
    }

    public function nextChangeDisplayDate(User $user): ?string
    {
        if (! $user->profile_image_change_available_at || $this->canChangeProfileImage($user)) {
            return null;
        }

        return $user->profile_image_change_available_at->format('F j, Y');
    }

    /**
     * @return array{
     *     picture_url: string,
     *     next_change_available_at: string,
     *     next_change_display: string,
     *     can_change: bool
     * }
     */
    public function upload(User $user, UploadedFile $file): array
    {
        $this->assertValidFile($file);

        if (! $this->canChangeProfileImage($user)) {
            $date = $user->profile_image_change_available_at?->format('F j, Y') ?? 'a later date';

            throw ValidationException::withMessages([
                'profile_picture' => [
                    "You can change your profile picture again on {$date}. Profile pictures can only be updated once every 30 days.",
                ],
            ]);
        }

        $oldPublicId = $user->profile_image_public_id;
        $publicId = 'user_'.$user->id;

        if ($this->cloudinary->isConfigured()) {
            $folder = trim((string) config('services.cloudinary.profile_folder', 'kabataan_profile_images'), '/');
            $candidates = array_values(array_unique(array_filter([
                $oldPublicId,
                $folder !== '' ? $folder.'/'.$publicId : null,
                $publicId,
            ])));

            foreach ($candidates as $candidate) {
                try {
                    $this->cloudinary->delete((string) $candidate);
                } catch (\Throwable) {
                    // Asset may not exist yet under this id.
                }
            }
        }

        $result = $this->uploadToCloudOrLocal($user, $file, $publicId);

        $uploadedAt = now();
        $nextChangeAt = $uploadedAt->copy()->addDays(30);

        $saved = $user->forceFill([
            'profile_image_url' => $result['url'],
            'profile_image_public_id' => $result['public_id'],
            'profile_image_uploaded_at' => $uploadedAt,
            'profile_image_change_available_at' => $nextChangeAt,
        ])->save();

        if (! $saved) {
            Log::error('Kabataan profile image database save failed', ['user_id' => $user->id]);
            throw ValidationException::withMessages([
                'profile_picture' => ['Failed to save profile picture. Please try again.'],
            ]);
        }

        $fresh = $user->fresh();

        if (! $fresh?->profile_image_url) {
            Log::error('Kabataan profile image URL missing after save', [
                'user_id' => $user->id,
                'public_id' => $result['public_id'],
            ]);
            throw ValidationException::withMessages([
                'profile_picture' => ['Failed to save profile picture. Please try again.'],
            ]);
        }

        if ($oldPublicId && $oldPublicId !== $result['public_id']) {
            $this->deleteStoredImage($oldPublicId);
        }

        Log::info('Kabataan profile image saved', [
            'user_id' => $user->id,
            'public_id' => $fresh->profile_image_public_id,
        ]);

        return [
            'picture_url' => $this->resolveDisplayUrl($fresh),
            'next_change_available_at' => $nextChangeAt->toIso8601String(),
            'next_change_display' => $nextChangeAt->format('F j, Y'),
            'can_change' => false,
        ];
    }

    /**
     * @return array{public_id: string, url: string}
     */
    private function uploadToCloudOrLocal(User $user, UploadedFile $file, string $publicId): array
    {
        if ($this->cloudinary->isConfigured()) {
            try {
                return $this->cloudinary->uploadProfileImage($file, $publicId);
            } catch (\Throwable $exception) {
                Log::error('Cloudinary profile upload failed', [
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);

                // Prefer a clear error when Cloudinary is configured — silent local
                // fallback often produced URLs that never displayed in production.
                throw ValidationException::withMessages([
                    'profile_picture' => [
                        'Unable to upload profile picture to cloud storage. Please try again.',
                    ],
                ]);
            }
        }

        return $this->uploadToLocalStorage($user, $file);
    }

    /**
     * @return array{public_id: string, url: string}
     */
    private function uploadToLocalStorage(User $user, UploadedFile $file): array
    {
        $extension = strtolower((string) ($file->guessExtension() ?: 'jpg'));
        $filename = 'user_'.$user->id.'.'.$extension;
        $directory = 'profile-images';

        Storage::disk('public')->putFileAs($directory, $file, $filename);

        $publicId = $directory.'/'.$filename;

        return [
            'public_id' => $publicId,
            'url' => '/storage/'.$publicId,
        ];
    }

    private function deleteStoredImage(string $publicId): void
    {
        if (str_starts_with($publicId, 'profile-images/')) {
            Storage::disk('public')->delete($publicId);

            return;
        }

        if (! $this->cloudinary->isConfigured()) {
            return;
        }

        $folder = trim((string) config('services.cloudinary.profile_folder', 'kabataan_profile_images'), '/');
        $candidates = [$publicId];
        if ($folder !== '' && ! str_starts_with($publicId, $folder.'/')) {
            $candidates[] = $folder.'/'.$publicId;
        }
        if ($folder !== '' && str_starts_with($publicId, $folder.'/')) {
            $candidates[] = substr($publicId, strlen($folder) + 1);
        }

        foreach (array_unique($candidates) as $candidate) {
            try {
                $this->cloudinary->delete($candidate);
            } catch (\Throwable $exception) {
                Log::warning('Failed to delete previous Kabataan profile image from Cloudinary', [
                    'public_id' => $candidate,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function assertValidFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'profile_picture' => ['Please select a valid image file.'],
            ]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'profile_picture' => ['Profile image must be 10MB or smaller.'],
            ]);
        }

        $mime = strtolower((string) $file->getMimeType());

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'profile_picture' => ['Only JPG, JPEG, PNG, and WEBP images are allowed.'],
            ]);
        }
    }
}
