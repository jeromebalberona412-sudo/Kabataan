<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatAutomation extends Model
{
    protected $table = 'chat_automations';

    protected $fillable = [
        'sk_official_id',
        'is_enabled',
    ];

    /**
     * PostgreSQL rejects integer 0/1 for native boolean columns (Supabase/pgbouncer).
     */
    protected function isEnabled(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            set: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        );
    }

    public function official(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sk_official_id');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ChatAutomationFaq::class, 'chat_automation_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
