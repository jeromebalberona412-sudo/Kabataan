<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('kabataan_registrations')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('evaluation_status')
                    ->orWhere('evaluation_status', '')
                    ->orWhereIn('evaluation_status', ['Not Profiled', 'Wrong Credentials']);
            })
            ->update(['status' => 'password_set']);
    }

    public function down(): void
    {
        // Non-reversible data normalization.
    }
};
