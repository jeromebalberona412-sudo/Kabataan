<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'profile_image_url')) {
                $table->text('profile_image_url')->nullable();
            }
            if (! Schema::hasColumn('users', 'profile_image_public_id')) {
                $table->string('profile_image_public_id', 255)->nullable();
            }
            if (! Schema::hasColumn('users', 'profile_image_uploaded_at')) {
                $table->timestamp('profile_image_uploaded_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'profile_image_change_available_at')) {
                $table->timestamp('profile_image_change_available_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'profile_image_url',
                'profile_image_public_id',
                'profile_image_uploaded_at',
                'profile_image_change_available_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
