<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramAccomplishmentImage extends Model
{
    protected $table = 'programs_accomplishment';

    protected $guarded = [];

    public function accomplishmentReport(): BelongsTo
    {
        return $this->belongsTo(ProgramAccomplishmentReport::class, 'accomplishment_report_id');
    }
}
