<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarshipQuickGuidelineStep extends Model
{
    protected $fillable = [
        'schedule_program_id',
        'step_order',
        'content_en',
        'content_tl',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
        ];
    }

    public function scheduleProgram(): BelongsTo
    {
        return $this->belongsTo(ScheduleProgram::class);
    }
}
