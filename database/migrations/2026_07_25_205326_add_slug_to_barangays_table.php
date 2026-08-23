<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('barangays', 'slug')) {
            Schema::table('barangays', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
            });
        }

        $barangays = DB::table('barangays')->orderBy('id')->get(['id', 'name', 'slug']);

        foreach ($barangays as $barangay) {
            if (filled($barangay->slug)) {
                continue;
            }

            DB::table('barangays')
                ->where('id', $barangay->id)
                ->update(['slug' => Str::slug($barangay->name)]);
        }

        Schema::table('barangays', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('barangays', 'slug')) {
            Schema::table('barangays', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            });
        }
    }
};
