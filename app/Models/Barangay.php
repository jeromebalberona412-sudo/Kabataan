<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Barangay extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'municipality',
        'province',
        'region',
        'tenant_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function abyips(): HasMany
    {
        return $this->hasMany(BarangayAbyip::class);
    }

    public function latestAbyip(): HasOne
    {
        return $this->hasOne(BarangayAbyip::class)->latestOfMany('year');
    }

    public function abyipDocuments(): HasMany
    {
        return $this->hasMany(Abyip::class)
            ->where('row_type', Abyip::ROW_DOCUMENT);
    }

    public function accomplishments(): HasMany
    {
        return $this->hasMany(BarangayAccomplishment::class);
    }

    public function latestAccomplishment(): HasOne
    {
        return $this->hasOne(BarangayAccomplishment::class)->latestOfMany('year');
    }

    public function accomplishmentDocuments(): HasMany
    {
        return $this->hasMany(Accomplishment::class)
            ->where('row_type', Accomplishment::ROW_DOCUMENT);
    }
}
