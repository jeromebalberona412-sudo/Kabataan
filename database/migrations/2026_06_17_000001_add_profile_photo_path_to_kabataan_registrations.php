<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kabataan_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('kabataan_registrations', 'profile_photo_path')) {
                $table->string('profile_photo_path', 500)->nullable()->after('contact_number');
            }
            if (! Schema::hasColumn('kabataan_registrations', 'facial_verification_completed_at')) {
                $table->timestamp('facial_verification_completed_at')->nullable()->after('profile_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kabataan_registrations', function (Blueprint $table) {
            $columns = ['profile_photo_path', 'facial_verification_completed_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('kabataan_registrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
