<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbyipItem extends Model
{
    protected $fillable = [
        'abyip_id',
        'sort_order',
        'row_type',
        'label',
        'ppa',
        'description',
        'expected_result',
        'performance_indicator',
        'period',
        'mooe',
        'co',
        'total',
        'person_responsible',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'mooe' => 'decimal:2',
            'co' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function abyip(): BelongsTo
    {
        return $this->belongsTo(BarangayAbyip::class, 'abyip_id');
    }
}
