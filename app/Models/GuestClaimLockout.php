<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestClaimLockout extends Model
{
    protected $fillable = [
        'device_token',
        'failed_attempts',
        'locked_until',
    ];

    protected $casts = [
        'failed_attempts' => 'integer',
        'locked_until' => 'datetime',
    ];
}
