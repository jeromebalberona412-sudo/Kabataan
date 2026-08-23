<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramSurvey extends Model
{
    protected $fillable = [
        'tenant_id',
        'barangay_id',
        'abyip_id',
        'abyip_program_id',
        'announcement',
        'instructions',
        'open_date',
        'close_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'open_date' => 'date',
            'close_date' => 'date',
        ];
    }

    public function abyipProgram(): BelongsTo
    {
        return $this->belongsTo(Abyip::class, 'abyip_program_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProgramSurveyQuestion::class, 'survey_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ProgramSurveyResponse::class, 'survey_id');
    }
}
