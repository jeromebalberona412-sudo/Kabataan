<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramEvaluation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'barangay_id',
        'created_by',
        'program_letter',
        'schedule_program_id',
        'abyip_program_id',
        'title',
        'instructions',
        'custom_questions',
        'status',
        'start_date',
        'end_date',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'custom_questions' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function scheduleProgram(): BelongsTo
    {
        return $this->belongsTo(ScheduleProgram::class);
    }

    public function abyipProgram(): BelongsTo
    {
        return $this->belongsTo(Abyip::class, 'abyip_program_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ProgramEvaluationResponse::class, 'evaluation_id');
    }
}
