<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KabataanSecurityQuestion extends Model
{
    protected $fillable = [
        'user_id',
        'kabataan_registration_id',
        'question_number',
        'question_text',
        'selected_choice',
        'custom_answer',
        'answer_hash',
    ];

    protected $hidden = [
        'answer_hash',
        'custom_answer',
        'selected_choice',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KabataanRegistration::class, 'kabataan_registration_id');
    }
}
