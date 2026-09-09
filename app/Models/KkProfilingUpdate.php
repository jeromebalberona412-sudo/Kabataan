<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KkProfilingUpdate extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $table = 'kk_profiling_updates';

    protected $fillable = [
        'kabataan_id',
        'year',
        'status',
        'started_at',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function kabataan(): BelongsTo
    {
        return $this->belongsTo(KabataanRegistration::class, 'kabataan_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
