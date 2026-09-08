<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $patimbaoZones = [
        'Sitio 1 Talarey',
        'Sitio 2',
        'Sitio 3 Banana',
        'Sitio 4 Highway',
        'Sitio 5 Silangan',
        'Sitio 6A Pulo',
        'Sitio 6B Ilaya',
        'Sitio 7 Bliss',
    ];

    /** @var list<string> */
    private array $sixSitioBarangays = [
        'Calios',
        'Palasan',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('barangay_zones') || ! Schema::hasTable('barangays')) {
            return;
        }

        $now = now();
        $fallbackTenantId = DB::table('tenants')->where('code', 'santa_cruz')->value('id') ?? 1;

        $barangays = DB::table('barangays')->orderBy('id')->get(['id', 'name', 'tenant_id']);

        foreach ($barangays as $barangay) {
            $zoneNames = $this->zonesForBarangay((string) $barangay->name);
            $tenantId = $barangay->tenant_id ?? $fallbackTenantId;

            foreach ($zoneNames as $zoneName) {
                DB::table('barangay_zones')->updateOrInsert(
                    [
                        'barangay_id' => $barangay->id,
                        'name' => $zoneName,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'type' => 'sitio',
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('barangay_zones') || ! Schema::hasTable('barangays')) {
            return;
        }

        $barangays = DB::table('barangays')->orderBy('id')->get(['id', 'name']);

        foreach ($barangays as $barangay) {
            $zoneNames = $this->zonesForBarangay((string) $barangay->name);

            DB::table('barangay_zones')
                ->where('barangay_id', $barangay->id)
                ->whereIn('name', $zoneNames)
                ->delete();
        }
    }

    /**
     * @return list<string>
     */
    private function zonesForBarangay(string $barangayName): array
    {
        if (strcasecmp($barangayName, 'Patimbao') === 0) {
            return $this->patimbaoZones;
        }

        foreach ($this->sixSitioBarangays as $sixSitioName) {
            if (strcasecmp($barangayName, $sixSitioName) === 0) {
                return $this->numberedSitios(6);
            }
        }

        return $this->numberedSitios(7);
    }

    /**
     * @return list<string>
     */
    private function numberedSitios(int $count): array
    {
        $zones = [];

        for ($i = 1; $i <= $count; $i++) {
            $zones[] = 'Sitio '.$i;
        }

        return $zones;
    }
};
