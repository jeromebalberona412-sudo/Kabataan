<?php

namespace App\Services;

use App\Models\BarangayZone;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class BarangayZoneService
{
    public function activeZonesForBarangay(int $barangayId): Collection
    {
        return BarangayZone::query()
            ->where('barangay_id', $barangayId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }

    /**
     * @return array<int, mixed>
     */
    public function purokZoneRules(int $barangayId): array
    {
        return [
            'required',
            'string',
            'max:150',
            Rule::exists('barangay_zones', 'name')->where(function ($query) use ($barangayId) {
                $query->where('barangay_id', $barangayId)
                    ->where('status', 'active');
            }),
        ];
    }
}
