<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramEvaluationResponse extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'evaluation_id',
        'registration_id',
        'answers',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ProgramEvaluation::class, 'evaluation_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KabataanRegistration::class, 'registration_id');
    }
}
