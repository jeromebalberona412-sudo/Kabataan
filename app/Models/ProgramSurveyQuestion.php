<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ProgramSurveyQuestion extends Model
{
    protected $fillable = [
        'survey_id',
        'question_label',
        'input_type',
        'is_required',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->getConnection()->getDriverName() !== 'pgsql') {
                return;
            }

            if (! array_key_exists('is_required', $model->attributes)) {
                return;
            }

            $bool = filter_var($model->attributes['is_required'], FILTER_VALIDATE_BOOLEAN);
            $model->attributes['is_required'] = DB::raw($bool ? 'TRUE' : 'FALSE');
        });
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(ProgramSurvey::class, 'survey_id');
    }
}
