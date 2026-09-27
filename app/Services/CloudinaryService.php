<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

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

        $result = $this->uploadImageAsset($path, $options, $file->getClientOriginalName());

        $version = isset($result['version']) ? (int) $result['version'] : null;

        return [
            'public_id' => $result['public_id'],
            'url' => $this->deliverUrl($result['public_id'], $version),
            'version' => $version,
        ];
    }

    /**
     * Upload a communication messenger image into the shared `communication` folder.
     *
     * @return array{public_id: string, url: string, version: int|null}
     */
    public function uploadCommunicationImage(UploadedFile $file): array
    {
        $this->ensureConfigured();

        $folder = trim((string) config('services.cloudinary.communication_folder', 'communication'), '/');
        $preset = trim((string) config('services.cloudinary.communication_upload_preset', ''));
        $path = $file->getRealPath() ?: $file->getPathname();
        $displayName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower((string) $file->getClientOriginalExtension());

        $options = [
            'folder' => $folder !== '' ? $folder : 'communication',
            'resource_type' => 'image',
            'overwrite' => false,
            'use_filename' => false,
            'unique_filename' => false,
            'display_name' => $displayName !== '' ? $displayName : 'communication_image',
        ];

        if ($ext === 'svg' && (bool) config('communications.attachments.allow_svg', false)) {
            $options['format'] = 'svg';
        }

        if ($preset !== '') {
            $options['upload_preset'] = $preset;
        }

        $result = $this->uploadImageAsset($path, $options, $file->getClientOriginalName());
        $version = isset($result['version']) ? (int) $result['version'] : null;
        $deliveryUrl = (string) ($result['secure_url'] ?? $result['url'] ?? '');

        if ($deliveryUrl === '') {
            $deliveryUrl = $this->deliverUrl((string) $result['public_id'], $version);
        }

        return [
            'public_id' => (string) $result['public_id'],
            'url' => $deliveryUrl,
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

        // Already has delivery transforms (slash or comma form from the SDK).
        if (
            str_contains($url, '/f_auto/')
            || str_contains($url, '/q_auto/')
            || str_contains($url, 'f_auto,q_auto')
            || str_contains($url, 'q_auto,f_auto')
            || preg_match('#/upload/[^/]*f_auto[^/]*/#', $url)
        ) {
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

        if (preg_match('#/image/upload/(.+)$#', $path, $matches)) {
            $segments = explode('/', $matches[1]);

            // Prefer everything after the version segment when present.
            foreach ($segments as $index => $segment) {
                if (preg_match('/^v\d+$/', $segment)) {
                    $segments = array_slice($segments, $index + 1);
                    break;
                }
            }

            // No version: skip leading transformation segments only.
            while ($segments !== [] && $this->isCloudinaryTransformationSegment($segments[0])) {
                array_shift($segments);
            }

            if ($segments === []) {
                return null;
            }

            $publicId = implode('/', $segments);

            return preg_replace('/\.[a-zA-Z0-9]+$/', '', $publicId) ?: null;
        }

        if (! str_contains($path, '/sk_oneportal/')) {
            return null;
        }

        if (! preg_match('#(/sk_oneportal/.+)$#', $path, $matches)) {
            return null;
        }

        return preg_replace('/\.[a-zA-Z0-9]+$/', '', ltrim($matches[1], '/')) ?: null;
    }

    /**
     * Detect Cloudinary transformation path segments (not public_id folders).
     */
    private function isCloudinaryTransformationSegment(string $segment): bool
    {
        if ($segment === '' || preg_match('/^v\d+$/', $segment)) {
            return true;
        }

        // e.g. c_fill,w_200,h_200
        if (str_contains($segment, ',')) {
            return true;
        }

        // Short flags: f_auto, q_auto, w_150, c_scale, fl_lossy, dpr_2.0
        // Avoid matching folder names like kabataan_profile_images (long prefix before _).
        return (bool) preg_match('/^(?:[a-z]{1,3}|fl|pg|dpr|dn|dl|e|t)_[a-zA-Z0-9.]+$/i', $segment);
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

        $filename = $source instanceof UploadedFile ? $source->getClientOriginalName() : basename($path);
        $result = $this->uploadImageAsset($path, $options, $filename);

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
     * Upload Kabataan profile images into CLOUDINARY_PROFILE_FOLDER.
     * Uses signed API upload with overwrite so the unsigned preset's
     * overwrite:false / unique_filename:false settings cannot block saves.
     *
     * @return array{public_id: string, url: string, version: int|null}
     */
    public function uploadProfileImage(UploadedFile $file, string $publicId): array
    {
        $this->ensureConfigured();

        $folder = trim((string) config('services.cloudinary.profile_folder', 'kabataan_profile_images'), '/');
        $baseId = trim(ltrim($publicId, '/'));
        // Keep folder in public_id so delete/display stay consistent.
        $fullPublicId = $folder !== ''
            ? (str_starts_with($baseId, $folder.'/') ? $baseId : $folder.'/'.$baseId)
            : $baseId;

        $path = $file->getRealPath() ?: $file->getPathname();
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new RuntimeException('Profile image file path is not readable.');
        }

        $options = [
            'public_id' => $fullPublicId,
            'resource_type' => 'image',
            'overwrite' => true,
            'invalidate' => true,
            'unique_filename' => false,
            'use_filename' => false,
            'type' => 'upload',
        ];

        $result = $this->uploadImageAsset($path, $options, $file->getClientOriginalName());

        $storedPublicId = (string) ($result['public_id'] ?? $fullPublicId);
        $version = isset($result['version']) ? (int) $result['version'] : null;
        $deliveryUrl = (string) ($result['secure_url'] ?? $result['url'] ?? '');

        if ($deliveryUrl === '') {
            $deliveryUrl = $this->deliverUrl($storedPublicId, $version);
        }

        // Prefer our delivery URL (f_auto/q_auto) so display is reliable in the app.
        try {
            $optimized = $this->deliverUrl($storedPublicId, $version);
            if ($optimized !== '') {
                $deliveryUrl = $optimized;
            }
        } catch (\Throwable) {
            // Keep Cloudinary secure_url if SDK delivery fails.
        }

        return [
            'public_id' => $storedPublicId,
            'url' => $deliveryUrl,
            'version' => $version,
        ];
    }

    /**
     * Signed image upload. Testing Cloudinary rejects the configured API secret,
     * so Invalid Signature retries through the existing unsigned image preset.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function uploadImageAsset(string $path, array $options, string $filename = 'image'): array
    {
        try {
            $result = $this->cloudinary->uploadApi()->upload($path, $options);
        } catch (Throwable $exception) {
            if (! str_contains($exception->getMessage(), 'Invalid Signature')) {
                throw $exception;
            }

            $result = $this->uploadUnsignedImage($path, $filename);
        }

        $resourceType = strtolower((string) ($result['resource_type'] ?? 'image'));
        if ($resourceType !== 'image') {
            throw new RuntimeException('Cloudinary stored the file as '.$resourceType.' instead of an image.');
        }

        return $result instanceof \ArrayAccess ? $result->getArrayCopy() : (array) $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadUnsignedImage(string $path, string $filename): array
    {
        $cloud = trim((string) config('services.cloudinary.cloud_name'));
        $preset = trim((string) config('services.cloudinary.profile_upload_preset', 'kabataan_profile_images'));
        if ($preset === '') {
            $preset = 'kabataan_profile_images';
        }

        $safeName = $filename !== '' ? $filename : 'image';

        $response = Http::timeout(60)
            ->attach('file', (string) file_get_contents($path), $safeName)
            ->post('https://api.cloudinary.com/v1_1/'.$cloud.'/image/upload', [
                'upload_preset' => $preset,
            ]);

        $payload = $response->json();
        if (! is_array($payload) || ! $response->successful() || ($payload['public_id'] ?? '') === '') {
            $message = is_array($payload) ? (string) ($payload['error']['message'] ?? 'Image upload failed.') : 'Image upload failed.';
            throw new RuntimeException($message);
        }

        if (strtolower((string) ($payload['resource_type'] ?? '')) !== 'image') {
            throw new RuntimeException('Cloudinary did not store the upload as an image.');
        }

        return $payload;
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
