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
    public function clearRegistrationCache(int $registrationId, ?int $userId = null, ?int $barangayId = null, ?int $profilingYear = null): void
    {
        Cache::forget("kk_profiling_history.max_year.{$registrationId}");

        if ($profilingYear !== null) {
            Cache::forget("kk_profiling_history.completed.{$registrationId}.{$profilingYear}");
        } else {
            $year = (int) now()->format('Y');
            Cache::forget("kk_profiling_history.completed.{$registrationId}.{$year}");
            Cache::forget("kk_profiling_history.completed.{$registrationId}.".($year - 1));
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
