<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleProgram extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'barangay_id',
        'created_by',
        'program_type',
        'committee',
        'program_name',
        'program_letter',
        'participation_quantity',
        'start_date',
        'end_date',
        'status',
        'announcement',
        'scholarship_details',
        'sports_details',
        'kk_profiling_fields',
        'custom_questions',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'participation_quantity' => 'integer',
            'scholarship_details' => 'array',
            'sports_details' => 'array',
            'kk_profiling_fields' => 'array',
            'custom_questions' => 'array',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ProgramApplication::class);
    }

    public function quickGuidelineSteps(): HasMany
    {
        return $this->hasMany(ScholarshipQuickGuidelineStep::class)->orderBy('step_order');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($builder) {
            $builder->whereRaw('"is_archived" = false')
                ->orWhereNull('is_archived');
        });
    }
}
