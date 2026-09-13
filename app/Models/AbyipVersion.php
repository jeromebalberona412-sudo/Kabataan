<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbyipVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'abyip_versions';

    protected $fillable = [
        'abyip_id',
        'tenant_id',
        'barangay_id',
        'version_number',
        'document_title',
        'document_content',
        'source_file_path',
        'source_type',
        'pdf_data',
        'pdf_path',
        'status',
        'created_by',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function abyip(): BelongsTo
    {
        return $this->belongsTo(Abyip::class, 'abyip_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return strtolower((string) $this->status) === self::STATUS_DRAFT;
    }

    public function getVersionLabelAttribute(): string
    {
        return 'v'.($this->version_number ?? 1);
    }
}
