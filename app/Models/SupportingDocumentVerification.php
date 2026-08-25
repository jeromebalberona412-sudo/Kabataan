<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportingDocumentVerification extends Model
{
    protected $table = 'supporting_document_verifications';

    protected $fillable = [
        'kabataan_registration_id',
        'wizard_token',
        'barangay_id',
        'document_type',
        'detected_document_type',
        'verification_fingerprint',
        'perceptual_hash',
        'verification_status',
        'detection_confidence',
        'duplicate_status',
        'needs_review',
        'review_reason',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'temp_storage_path',
        'retain_until',
    ];

    protected $hidden = [
        'verification_fingerprint',
        'perceptual_hash',
        'temp_storage_path',
    ];

    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
            'detection_confidence' => 'float',
            'reviewed_at' => 'datetime',
            'retain_until' => 'datetime',
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(SupportingDocumentAuditLog::class, 'supporting_document_verification_id');
    }
}
