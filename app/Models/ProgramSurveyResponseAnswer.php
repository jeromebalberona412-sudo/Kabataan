<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramSurveyResponseAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'response_id',
        'question_id',
        'answer',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(ProgramSurveyResponse::class, 'response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ProgramSurveyQuestion::class, 'question_id');
    }
}
