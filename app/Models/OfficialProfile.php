<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialProfile extends Model
{
    protected $table = 'official_profiles';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
