<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramAccomplishmentReport extends Model
{
    public const STATUS_PUBLISHED = 'Published';

    protected $table = 'programs_accomplishment_reports';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'participants_count' => 'integer',
            'target_beneficiaries' => 'integer',
            'actual_expense' => 'decimal:2',
            'approved_budget' => 'decimal:2',
            'actual_implementation_date' => 'date',
            'actual_completion_date' => 'date',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ScheduleProgram::class, 'program_id');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProgramAccomplishmentImage::class, 'accomplishment_report_id')->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProgramAccomplishmentDocument::class, 'accomplishment_report_id');
    }

    public function publicDocuments(): HasMany
    {
        return $this->documents()->where('visibility', ProgramAccomplishmentDocument::VISIBILITY_PUBLIC);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopePubliclyVisible($query)
    {
        return $query->whereIn('status', [self::STATUS_PUBLISHED, 'Submitted']);
    }

    public function plannedBudget(): float
    {
        if ($this->approved_budget !== null) {
            return (float) $this->approved_budget;
        }

        return (float) ($this->program?->participation_quantity ?? 0);
    }

    public function remainingBudget(): float
    {
        return max(0, $this->plannedBudget() - (float) $this->actual_expense);
    }
}
