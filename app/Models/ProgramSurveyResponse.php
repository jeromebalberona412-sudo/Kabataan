<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramSurveyResponse extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'survey_id',
        'registration_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(ProgramSurvey::class, 'survey_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KabataanRegistration::class, 'registration_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ProgramSurveyResponseAnswer::class, 'response_id');
    }
}
