<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BarangayLogoUrlService
{
    /** @var array<int, string|null> */
    private array $resolved = [];

    public function __construct(private readonly CloudinaryService $cloudinary)
    {
    }

    public function resolve(?int $barangayId): ?string
    {
        if (!$barangayId) {
            return null;
        }

        if (array_key_exists($barangayId, $this->resolved)) {
            return $this->resolved[$barangayId];
        }

        $logo = DB::table('barangay_logos')
            ->where('barangay_id', $barangayId)
            ->orderByDesc('updated_at')
            ->first(['url', 'cloudinary_public_id', 'cloudinary_version', 'updated_at']);

        if (!$logo) {
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
    }
}
