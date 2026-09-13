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
