<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramAccomplishmentDocument extends Model
{
    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITY_PUBLIC = 'public';

    protected $table = 'programs_accomplishment_documents';

    protected $fillable = [
        'accomplishment_report_id',
        'original_name',
        'stored_path',
        'mime_type',
        'file_size',
        'document_type',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function accomplishmentReport(): BelongsTo
    {
        return $this->belongsTo(ProgramAccomplishmentReport::class, 'accomplishment_report_id');
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }
}
