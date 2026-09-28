<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BarangayLogoUrlService
{
    /** @var array<int, string|null> */
    private array $resolved = [];

    public function __construct(private readonly CloudinaryService $cloudinary)
    {
    }

    /**
     * @param  list<int>  $barangayIds
     * @return array<int, string|null>
     */
    public function resolveMany(array $barangayIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $barangayIds))));
        $logos = [];
        foreach ($ids as $id) {
            $logos[$id] = null;
        }

        if ($ids === []) {
            return $logos;
        }

        try {
            if (! Schema::hasTable('barangay_logos')) {
                return $logos;
            }

            $rows = DB::table('barangay_logos')
                ->whereIn('barangay_id', $ids)
                ->orderByDesc('updated_at')
                ->get(['barangay_id', 'url', 'cloudinary_public_id', 'cloudinary_version', 'updated_at']);

            foreach ($rows as $logo) {
                $barangayId = (int) $logo->barangay_id;
                if (array_key_exists($barangayId, $this->resolved) || ($logos[$barangayId] ?? null) !== null) {
                    continue;
                }

                $url = $logo->url;
                if ($logo->cloudinary_public_id && $this->cloudinary->isConfigured()) {
                    $version = $logo->cloudinary_version
                        ? (int) $logo->cloudinary_version
                        : $this->cloudinary->extractVersionFromUrl((string) $logo->url);
                    $url = $this->cloudinary->deliverUrl($logo->cloudinary_public_id, $version);
                }

                $logos[$barangayId] = $this->resolved[$barangayId] = CloudinaryService::cacheBust((string) $url, $logo->updated_at);
            }
        } catch (Throwable $e) {
            Log::warning('Barangay logo batch resolve failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $logos;
    }

    public function resolve(?int $barangayId): ?string
    {
        if (! $barangayId) {
            return null;
        }

        if (array_key_exists($barangayId, $this->resolved)) {
            return $this->resolved[$barangayId];
        }

        try {
            if (! Schema::hasTable('barangay_logos')) {
                return $this->resolved[$barangayId] = null;
            }

            $logo = DB::table('barangay_logos')
                ->where('barangay_id', $barangayId)
                ->orderByDesc('updated_at')
                ->first(['url', 'cloudinary_public_id', 'cloudinary_version', 'updated_at']);

            if (! $logo) {
                return $this->resolved[$barangayId] = null;
            }

            $url = $logo->url;

            if ($logo->cloudinary_public_id && $this->cloudinary->isConfigured()) {
                $version = $logo->cloudinary_version
                    ? (int) $logo->cloudinary_version
                    : $this->cloudinary->extractVersionFromUrl((string) $logo->url);

                $url = $this->cloudinary->deliverUrl($logo->cloudinary_public_id, $version);
            }

            return $this->resolved[$barangayId] = CloudinaryService::cacheBust((string) $url, $logo->updated_at);
        } catch (Throwable $e) {
            Log::warning('Barangay logo resolve failed', [
                'barangay_id' => $barangayId,
                'error' => $e->getMessage(),
            ]);

            return $this->resolved[$barangayId] = null;
        }
    }
}
