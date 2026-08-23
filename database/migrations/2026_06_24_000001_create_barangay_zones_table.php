<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 20)->default('purok');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['barangay_id', 'name']);
            $table->index('tenant_id');
            $table->index('barangay_id');
        });

        $caliosZones = [
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

        $calios = DB::table('barangays')->where('name', 'Calios')->first();

        if ($calios) {
            $now = now();
            foreach ($caliosZones as $zoneName) {
                DB::table('barangay_zones')->updateOrInsert(
                    [
                        'barangay_id' => $calios->id,
                        'name'        => $zoneName,
                    ],
                    [
                        'tenant_id'  => $calios->tenant_id ?? 1,
                        'type'       => 'purok',
                        'status'     => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_zones');
    }
};
