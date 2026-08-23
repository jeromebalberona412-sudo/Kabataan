<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 30)->default('kabataan')->after('password');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 30)->default('ACTIVE')->after('role');
            }

            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('status');
            }

            if (! Schema::hasColumn('users', 'barangay_id')) {
                $table->unsignedBigInteger('barangay_id')->nullable()->after('tenant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['barangay_id', 'tenant_id', 'status', 'role'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
