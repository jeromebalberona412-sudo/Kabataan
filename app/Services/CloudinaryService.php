<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    private string $folder;

    public function __construct()
    {
        $this->folder = (string) config('services.cloudinary.folder', 'sk_oneportal/uploads');
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
        ]);
    }

    public function isConfigured(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    /**
     * @return array{public_id: string, url: string, version: int|null}
     */
    public function upload(UploadedFile $file, string $publicId, bool $invalidate = false): array
    {
        $this->ensureConfigured();

        $options = [
            'public_id' => $this->folder.'/'.$publicId,
            'overwrite' => true,
            'resource_type' => 'image',
        ];

        if ($invalidate) {
            $options['invalidate'] = true;
        }

        $path = $file->getRealPath() ?: $file->getPathname();

        $result = $this->cloudinary->uploadApi()->upload(
            $path,
            $options
        );

        $version = isset($result['version']) ? (int) $result['version'] : null;

        return [
            'public_id' => $result['public_id'],
            'url' => $this->deliverUrl($result['public_id'], $version),
            'version' => $version,
        ];
    }

    public function delete(string $publicId): void
    {
        $this->ensureConfigured();
        $this->cloudinary->uploadApi()->destroy($publicId, ['invalidate' => true]);
    }

    public function deliverUrl(string $publicId, ?int $version = null): string
    {
        $this->ensureConfigured();

        $image = $this->cloudinary
            ->image($publicId)
            ->format('auto')
            ->quality('auto');

        if ($version && $version > 0) {
            $image->version($version);
        }

        return (string) $image->toUrl();
    }

    public function normalizeUrl(?string $url): ?string
    {
        if (! $url || ! $this->isConfigured()) {
            return $url;
        }

        if (! str_contains($url, 'res.cloudinary.com')) {
            return $url;
        }

        // If URL already has transformations (f_auto, q_auto), return as-is
        // This prevents duplicate transformation parameters
        if (str_contains($url, '/f_auto/') || str_contains($url, '/q_auto/')) {
            return $url;
        }

        $publicId = $this->extractPublicIdFromUrl($url);

        return $publicId
            ? $this->deliverUrl($publicId, $this->extractVersionFromUrl($url))
            : $url;
    }

    public function extractPublicIdFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#/image/upload/(?:v\d+/)?(.+)$#', $path, $matches)) {
            return preg_replace('/\.[a-zA-Z0-9]+$/', '', $matches[1]) ?: null;
        }

        if (! str_contains($path, '/sk_oneportal/')) {
            return null;
        }

        if (! preg_match('#(/sk_oneportal/.+)$#', $path, $matches)) {
            return null;
        }

        return preg_replace('/\.[a-zA-Z0-9]+$/', '', ltrim($matches[1], '/')) ?: null;
    }

    public function extractVersionFromUrl(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('#/v(\d+)/#', $path, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function cacheBust(string $url, DateTimeInterface|string|int|null $updatedAt = null): string
    {
        if ($url === '' || str_starts_with($url, 'data:')) {
            return $url;
        }

        $token = match (true) {
            $updatedAt instanceof DateTimeInterface => $updatedAt->getTimestamp(),
            is_int($updatedAt) => $updatedAt,
            is_string($updatedAt) && $updatedAt !== '' => strtotime($updatedAt) ?: time(),
            default => time(),
        };

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'cb='.$token;
    }

    /**
     * @return array{public_id: string, url: string, version: int|null}
     */
    public function uploadSupportingDocument(UploadedFile|string $source, string $publicId, ?string $displayName = null): array
    {
        $this->ensureConfigured();

        $path = $source instanceof UploadedFile
            ? ($source->getRealPath() ?: $source->getPathname())
            : $source;
        $folder = trim((string) config('services.cloudinary.supporting_docs_folder', 'Supporting_Documents'), '/');
        $preset = trim((string) config('services.cloudinary.supporting_docs_upload_preset', 'Supporting_Documents'));

        if ($displayName === null && $source instanceof UploadedFile) {
            $displayName = pathinfo($source->getClientOriginalName(), PATHINFO_FILENAME);
        }

        $options = [
            'folder' => $folder,
            'public_id' => ltrim($publicId, '/'),
            'resource_type' => 'image',
            'overwrite' => false,
            'use_filename' => false,
            'unique_filename' => false,
        ];

        if ($displayName) {
            $options['display_name'] = $displayName;
        }

        if ($preset !== '') {
            $options['upload_preset'] = $preset;
        }

        $result = $this->cloudinary->uploadApi()->upload($path, $options);

        $version = isset($result['version']) ? (int) $result['version'] : null;
        $deliveryUrl = (string) ($result['secure_url'] ?? $result['url'] ?? '');

        if ($deliveryUrl === '') {
            $deliveryUrl = $this->deliverUrl($result['public_id'], $version);
        }

        return [
            'public_id' => $result['public_id'],
            'url' => $deliveryUrl,
            'version' => $version,
        ];
    }

    /**
     * @return array{public_id: string, url: string, version: int|null}
     */
    public function uploadProfileImage(UploadedFile $file, string $publicId): array
    {
        $this->ensureConfigured();

        $folder = trim((string) config('services.cloudinary.profile_folder', 'kabataan_profile_images'), '/');
        $preset = trim((string) config('services.cloudinary.profile_upload_preset', 'kabataan_profile_images'));
        $path = $file->getRealPath() ?: $file->getPathname();
        $displayName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $options = [
            'folder' => $folder,
            'public_id' => ltrim($publicId, '/'),
            'resource_type' => 'image',
            'overwrite' => false,
            'use_filename' => false,
            'unique_filename' => false,
            'display_name' => $displayName !== '' ? $displayName : $publicId,
        ];

        if ($preset !== '') {
            $options['upload_preset'] = $preset;
        }

        $result = $this->cloudinary->uploadApi()->upload($path, $options);

        $version = isset($result['version']) ? (int) $result['version'] : null;
        $deliveryUrl = (string) ($result['secure_url'] ?? $result['url'] ?? '');

        if ($deliveryUrl === '') {
            $deliveryUrl = $this->deliverUrl($result['public_id'], $version);
        }

        return [
            'public_id' => $result['public_id'],
            'url' => $deliveryUrl,
            'version' => $version,
        ];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Cloudinary is not configured. Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET in .env.'
            );
        }
    }
}
