<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $caliosPuroks = [
        'BAYSIDE',
        'VILLA GRACIA',
        'IMELDA',
        'LUPANG PANGAKO',
        'DAMAYAN',
        'MARCELO',
        'BIGAYAN VILLA ROSA',
        'PHASE 3',
        'BIGAYAN SAN LUIS',
    ];

    /** @var list<string> */
    private array $patimbaoSitios = [
        'Sitio 1 Talarey',
        'Sitio 2',
        'Sitio 3 Banana',
        'Sitio 4 Highway',
        'Sitio 5 Silangan',
        'Sitio 6A Pulo',
        'Sitio 6B Ilaya',
        'Sitio 7 Bliss',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('barangay_zones') || ! Schema::hasTable('barangays')) {
            throw new \RuntimeException('The barangay_zones and barangays tables must exist before seeding zones.');
        }

        $fallbackTenantId = DB::table('tenants')->where('code', 'santa_cruz')->value('id');
        $now = now();
        $rows = [];

        foreach (DB::table('barangays')->orderBy('id')->get(['id', 'name', 'tenant_id']) as $barangay) {
            $barangayName = (string) $barangay->name;
            $tenantId = $barangay->tenant_id ?? $fallbackTenantId;

            if ($tenantId === null) {
                throw new \RuntimeException("No tenant is configured for barangay {$barangayName}.");
            }

            if (strcasecmp($barangayName, 'Calios') === 0) {
                foreach ($this->caliosPuroks as $name) {
                    $rows[] = $this->zoneRow((int) $barangay->id, (int) $tenantId, $name, 'purok', $now);
                }
            }

            $sitios = match (strtolower($barangayName)) {
                'patimbao' => $this->patimbaoSitios,
                'calios', 'palasan' => $this->numberedSitios(6),
                default => $this->numberedSitios(7),
            };

            foreach ($sitios as $name) {
                $rows[] = $this->zoneRow((int) $barangay->id, (int) $tenantId, $name, 'sitio', $now);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('barangay_zones')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Zone entries may be edited after migration, so rollback leaves this reference data intact.
    }

    /**
     * @return array<string, mixed>
     */
    private function zoneRow(int $barangayId, int $tenantId, string $name, string $type, mixed $now): array
    {
        return [
            'tenant_id' => $tenantId,
            'barangay_id' => $barangayId,
            'name' => $name,
            'type' => $type,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return list<string>
     */
    private function numberedSitios(int $count): array
    {
        $sitios = [];

        for ($number = 1; $number <= $count; $number++) {
            $sitios[] = 'Sitio '.$number;
        }

        return $sitios;
    }
};
