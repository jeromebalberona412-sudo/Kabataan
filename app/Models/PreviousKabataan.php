<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreviousKabataan extends Model
{
    protected $table = 'previous_kabataan';

    protected $fillable = [
        'kabataan_registration_id',
        'tenant_id',
        'barangay_id',
        'moved_by_user_id',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'age',
        'birthday',
        'sex',
        'civil_status',
        'youth_classification',
        'youth_age_group',
        'email',
        'contact_number',
        'home_address',
        'education',
        'work_status',
        'registered_voter',
        'voted_last_election',
        'kk_assembly',
        'kk_assembly_count',
        'barangay_name',
        'region',
        'province',
        'city',
        'form_data',
        'profiling_year',
        'moved_at',
    ];

    protected $casts = [
        'form_data' => 'array',
        'moved_at' => 'datetime',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function scopeForBarangay($query, int $barangayId)
    {
        return $query->where('barangay_id', $barangayId);
    }
}
