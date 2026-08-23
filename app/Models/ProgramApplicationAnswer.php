<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramApplicationAnswer extends Model
{
    protected $fillable = [
        'program_application_id',
        'question_id',
        'question_label',
        'question_type',
        'answer_text',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ProgramApplication::class, 'program_application_id');
    }
}
