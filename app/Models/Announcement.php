<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Announcement extends Model
{
    protected $table = 'community_feeds';

    protected $fillable = [
        'user_id',
        'barangay_id',
        'type',
        'title',
        'body',
        'link_url',
        'share_token',
        'is_federation_wide',
        'visibility',
        'is_archived',
        'archived_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_federation_wide' => 'boolean',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(AnnouncementReaction::class, 'community_feed_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class, 'community_feed_id')->orderBy('created_at');
    }

    public function images(): HasMany
    {
        return $this->hasMany(AnnouncementImage::class, 'community_feed_id')->orderBy('sort_order');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(AnnouncementVideo::class, 'community_feed_id')->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereRaw('"is_archived" = false');
    }

    public function scopeVisibleToTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where(function (Builder $tenantPosts) use ($tenantId): void {
            $tenantPosts
                ->where(function (Builder $federationPosts) use ($tenantId): void {
                    $federationPosts
                        ->whereRaw('"is_federation_wide" = true')
                        ->whereHas('user', fn (Builder $user) => $user->where('tenant_id', $tenantId));
                })
                ->orWhere(function (Builder $barangayPosts) use ($tenantId): void {
                    $barangayPosts
                        ->where(function (Builder $specific) {
                            $specific->whereRaw('"is_federation_wide" = false')
                                ->orWhereNull('is_federation_wide');
                        })
                        ->whereHas('barangay', fn (Builder $barangay) => $barangay->where('tenant_id', $tenantId));
                });
        });
    }

    public function isPublicAudience(): bool
    {
        if (! Schema::hasColumn('community_feeds', 'visibility')) {
            return true;
        }

        return ($this->visibility ?: 'public') === 'public';
    }

    public function isShareable(): bool
    {
        if ((bool) $this->is_archived) {
            return false;
        }

        if (! Schema::hasColumn('community_feeds', 'share_token')) {
            return false;
        }

        return filled($this->share_token) && $this->isPublicAudience();
    }
}
