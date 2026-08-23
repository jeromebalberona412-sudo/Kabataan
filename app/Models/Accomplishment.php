<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Accomplishment extends Model
{
    public const ROW_DOCUMENT = 'document';

    public const ROW_EXPENDITURE = 'expenditure';

    public const ROW_YOUTH_PROGRAM = 'youth_program';

    public const ROW_ACTIVITY = 'activity';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'accomplishments';

    protected $fillable = [
        'document_id',
        'tenant_id',
        'barangay_id',
        'created_by',
        'fiscal_year',
        'row_type',
        'parent_id',
        'code',
        'program_name',
        'activity_name',
        'description',
        'expected_result',
        'performance_indicator',
        'implementation_period',
        'implementation_start',
        'implementation_end',
        'person_responsible',
        'mooe',
        'co',
        'total',
        'budget',
        'sort_order',
        'barangay_estimated_budget',
        'sk_fund_amount',
        'total_budget',
        'prepared_by',
        'prepared_by_name',
        'prepared_position',
        'prepared_by_position',
        'approved_by',
        'approved_by_name',
        'approved_position',
        'approved_by_position',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'mooe' => 'decimal:2',
            'co' => 'decimal:2',
            'total' => 'decimal:2',
            'budget' => 'decimal:2',
            'barangay_estimated_budget' => 'decimal:2',
            'sk_fund_amount' => 'decimal:2',
            'total_budget' => 'decimal:2',
        ];
    }

    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('row_type', self::ROW_DOCUMENT);
    }

    public function getProgramLetterAttribute(): ?string
    {
        $code = strtoupper(trim((string) $this->code));

        return preg_match('/^[A-J]$/', $code) === 1 ? $code : null;
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(self::class, 'document_id')
            ->where('row_type', '!=', self::ROW_DOCUMENT)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('row_type', self::ROW_ACTIVITY)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
