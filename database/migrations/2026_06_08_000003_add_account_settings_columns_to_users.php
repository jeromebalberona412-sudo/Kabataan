<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'pending_email')) {
                $table->string('pending_email')->nullable();
            }
            if (! Schema::hasColumn('users', 'email_change_token')) {
                $table->string('email_change_token')->nullable();
            }
            if (! Schema::hasColumn('users', 'email_change_token_expires_at')) {
                $table->timestamp('email_change_token_expires_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'email_change_verified_at')) {
                $table->timestamp('email_change_verified_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'email_change_last_sent_at')) {
                $table->timestamp('email_change_last_sent_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'pending_password')) {
                $table->string('pending_password')->nullable();
            }
            if (! Schema::hasColumn('users', 'password_change_token')) {
                $table->string('password_change_token')->nullable();
            }
            if (! Schema::hasColumn('users', 'password_change_token_expires_at')) {
                $table->timestamp('password_change_token_expires_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'password_change_last_sent_at')) {
                $table->timestamp('password_change_last_sent_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'pending_email',
                'email_change_token',
                'email_change_token_expires_at',
                'email_change_verified_at',
                'email_change_last_sent_at',
                'pending_password',
                'password_change_token',
                'password_change_token_expires_at',
                'password_change_last_sent_at',
                'must_change_password',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
