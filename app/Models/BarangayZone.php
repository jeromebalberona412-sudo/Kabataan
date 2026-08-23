<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangayZone extends Model
{
    protected $fillable = [
        'tenant_id',
        'barangay_id',
        'name',
        'type',
        'status',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
