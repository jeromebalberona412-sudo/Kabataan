<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KabataanProfilingHistory extends Model
{
    protected $table = 'kabataan_profiling_history';

    protected $fillable = [
        'kabataan_registration_id',
        'profiling_year',
        'kk_profiling_schedule_id',
        'form_data',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'email',
        'contact_number',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'submitted_at' => 'datetime',
            'profiling_year' => 'integer',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KabataanRegistration::class, 'kabataan_registration_id');
    }
}
