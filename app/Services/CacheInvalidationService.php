<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheInvalidationService
{
    /**
     * Clear all cache keys related to a specific user
     */
    public function clearUserCache(int $userId): void
    {
        Cache::forget("kabataan_registration.latest.{$userId}");
    }

    /**
     * Clear all cache keys related to a specific registration
     */
    public function clearRegistrationCache(int $registrationId, ?int $userId = null, ?int $barangayId = null): void
    {
        Cache::forget("kk_profiling_history.max_year.{$registrationId}");
        
        // Clear all profiling completion caches for this registration
        $cacheStore = Cache::getStore();
        if (method_exists($cacheStore, 'getPrefix')) {
            $prefix = $cacheStore->getPrefix();
            // Note: This is a simplified approach. For production, consider using cache tags
            // or a more sophisticated cache invalidation strategy
        }
        
        if ($userId) {
            $this->clearUserCache($userId);
        }
        
        if ($barangayId) {
            $this->clearBarangayCache($barangayId);
        }
    }

    /**
     * Clear all cache keys related to a specific barangay
     */
    public function clearBarangayCache(int $barangayId): void
    {
        Cache::forget("barangay_sk_profiles.officials.{$barangayId}");
        Cache::forget("abyip.latest_document.{$barangayId}");
        
        // Clear profiling schedule cache for this barangay
        $today = now()->toDateString();
        Cache::forget("kk_profiling_schedule.{$barangayId}.{$today}");
    }

    /**
     * Clear tenant-wide cache
     */
    public function clearTenantCache(int $tenantId): void
    {
        Cache::forget("barangay_sk_profiles.list.{$tenantId}");
    }

    /**
     * Clear authentication configuration cache
     */
    public function clearAuthConfigCache(): void
    {
        Cache::forget('kabataan_auth.allowed_roles');
        Cache::forget('kabataan_auth.blocked_emails');
    }
}