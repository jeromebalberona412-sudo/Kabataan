<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class KabataanRegistration extends Model
{
    use SoftDeletes;
    
    protected static function booted()
    {
        static::updated(function ($registration) {
            // Clear user-specific cache when registration is updated
            if ($registration->user_id) {
                Cache::forget("kabataan_registration.latest.{$registration->user_id}");
                Cache::forget("kk_profiling_history.max_year.{$registration->id}");
                Cache::forget("kk_profiling_history.completed.{$registration->id}.*");
            }
            
            // Clear barangay-specific cache
            if ($registration->barangay_id) {
                Cache::forget("kk_profiling_schedule.{$registration->barangay_id}.*");
            }
        });
        
        static::deleted(function ($registration) {
            // Clear user-specific cache when registration is deleted
            if ($registration->user_id) {
                Cache::forget("kabataan_registration.latest.{$registration->user_id}");
                Cache::forget("kk_profiling_history.max_year.{$registration->id}");
                Cache::forget("kk_profiling_history.completed.{$registration->id}.*");
            }
            
            // Clear barangay-specific cache
            if ($registration->barangay_id) {
                Cache::forget("kk_profiling_schedule.{$registration->barangay_id}.*");
            }
        });
    }
    
    protected $fillable = [
        'tenant_id',
        'barangay_id',
        'user_id',
        'reviewed_by_user_id',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'email',
        'contact_number',
        'profile_photo_path',
        'facial_verification_completed_at',
        'respondent_number',
        'respondent_sequence',
        'form_data',
        'status',
        'evaluation_status',
        'evaluation_notes',
        'submitted_at',
        'email_verified_at',
        'password_set_at',
        'reviewed_at',
        'review_notes',
        'archived_at',
        'archive_reason',
    ];

    protected $casts = [
        'form_data'        => 'array',
        'evaluation_notes' => 'array',
        'submitted_at'     => 'datetime',
        'email_verified_at' => 'datetime',
        'facial_verification_completed_at' => 'datetime',
        'password_set_at'  => 'datetime',
        'reviewed_at'      => 'datetime',
        'archived_at'      => 'datetime',
        'deleted_at'       => 'datetime',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function scopeForBarangay($query, int $barangayId)
    {
        return $query->where('barangay_id', $barangayId);
    }

    public function markEmailVerified(): void
    {
        $this->update([
            'status' => 'email_verified',
            'email_verified_at' => now(),
        ]);
    }

    public function markPasswordSet(): void
    {
        $this->update([
            'status' => 'password_set',
            'password_set_at' => now(),
        ]);
    }

    public function linkUser(int $userId): void
    {
        $this->update([
            'user_id' => $userId,
        ]);
    }

    public function markActive(int $userId): void
    {
        $this->update([
            'status' => 'active',
            'user_id' => $userId,
        ]);
    }

    public function getFullNameAttribute(): string
    {
        $name = $this->first_name . ' ';
        if ($this->middle_name) {
            $name .= substr($this->middle_name, 0, 1) . '. ';
        }
        $name .= $this->last_name;
        if ($this->suffix) {
            $name .= ' ' . $this->suffix;
        }
        return $name;
    }
}
