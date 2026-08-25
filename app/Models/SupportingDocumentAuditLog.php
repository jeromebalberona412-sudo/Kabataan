<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportingDocumentAuditLog extends Model
{
    protected $table = 'supporting_document_audit_logs';

    protected $fillable = [
        'supporting_document_verification_id',
        'actor_id',
        'action',
        'reason',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(SupportingDocumentVerification::class, 'supporting_document_verification_id');
    }
}
