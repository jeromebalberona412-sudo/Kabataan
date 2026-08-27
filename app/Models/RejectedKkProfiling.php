<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RejectedKkProfiling extends Model
{
    protected $table = 'rejected_kk_profiling';

    protected $fillable = [
        'kabataan_registration_id',
        'tenant_id',
        'barangay_id',
        'rejected_by_user_id',
        'rejection_reason',
        'rejection_remarks',
        'previous_registration_status',
        'previous_evaluation_status',
        'previous_user_status',
        'rejected_at',
        'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'rejected_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KabataanRegistration::class, 'kabataan_registration_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('restored_at');
    }

    public function scopeForBarangay($query, int $barangayId)
    {
        return $query->where('barangay_id', $barangayId);
    }
}
