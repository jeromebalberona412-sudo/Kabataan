<?php

namespace App\Modules\Communications\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatAutomationFaq extends Model
{
    use SoftDeletes;

    protected $table = 'chat_automation_faqs';

    protected $fillable = [
        'chat_automation_id',
        'question',
        'automated_response',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * PostgreSQL rejects integer 0/1 for native boolean columns (Supabase/pgbouncer).
     */
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            set: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        );
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(ChatAutomation::class, 'chat_automation_id');
    }
}
