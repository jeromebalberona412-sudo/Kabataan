<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarangayAccomplishment extends Model
{
    protected $table = 'accomplishments';

    protected $fillable = [
        'barangay_id',
        'year',
        'estimated_budget',
        'sk_fund',
        'total_expenditure',
        'chairperson_name',
        'chairperson_title',
        'approved_by_name',
        'approved_by_title',
        'source_pdf_path',
        'extracted_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'estimated_budget' => 'decimal:2',
            'sk_fund' => 'decimal:2',
            'total_expenditure' => 'decimal:2',
            'extracted_at' => 'datetime',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccomplishmentItem::class, 'accomplishment_id')->orderBy('sort_order');
    }
}
