<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Historical no-op.
 *
 * An earlier revision briefly dropped kk_profiling_updates. Annual update tracking
 * is restored by 2026_09_09_090000_create_kk_profiling_updates_table.
 * Do not drop that table from this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty.
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};
