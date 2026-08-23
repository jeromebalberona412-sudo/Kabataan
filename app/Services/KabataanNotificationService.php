<?php

namespace App\Services;

use App\Models\KabataanNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class KabataanNotificationService
{
    public const CATEGORY_KK_PROFILING = 'kk_profiling';

    public const CATEGORY_PROGRAM = 'program';

    public const CATEGORY_REMINDER = 'reminder';

    public const CATEGORY_ANNOUNCEMENT = 'announcement';

    public const CATEGORY_GENERAL = 'general';

    /**
     * @return list<array{id: int, category: string, category_label: string, title: string, text: string, time: string, unread: bool, action_url: string|null}>
     */
    public function recentForUser(?User $user, int $limit = 8): array
    {
        if (! $user || ! $this->tableExists()) {
            return [];
        }

        return $this->forUserQuery($user)
            ->limit($limit)
            ->get()
            ->map(fn (KabataanNotification $notification) => $this->formatNotification($notification))
            ->all();
    }

    /**
     * @return list<array{id: int, category: string, category_label: string, title: string, text: string, time: string, unread: bool, action_url: string|null}>
     */
    public function allForUser(?User $user): array
    {
        if (! $user || ! $this->tableExists()) {
            return [];
        }

        return $this->forUserQuery($user)
            ->get()
            ->map(fn (KabataanNotification $notification) => $this->formatNotification($notification))
            ->all();
    }

    public function unreadCountForUser(?User $user): int
    {
        if (! $user || ! $this->tableExists()) {
            return 0;
        }

        return $this->forUserQuery($user)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(User $user, int $notificationId): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        $notification = KabataanNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($notificationId)
            ->first();

        if (! $notification || $notification->read_at !== null) {
            return false;
        }

        $notification->update(['read_at' => now()]);

        return true;
    }

    public function markAllRead(User $user): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        return KabataanNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function notifyUser(
        User $user,
        string $category,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): ?KabataanNotification {
        if (! $this->tableExists()) {
            return null;
        }

        return KabataanNotification::create([
            'user_id' => $user->id,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);
    }

    public function notifyKkProfilingUpdated(User $user, int $profilingYear, string $barangayName): void
    {
        $this->notifyUser(
            $user,
            self::CATEGORY_KK_PROFILING,
            'KK Profiling Updated',
            "Congratulations! You've successfully updated your KK Profiling for {$profilingYear} in {$barangayName}.",
            route('profile'),
        );
    }

    public function notifyRegistrationApproved(User $user, string $barangayName): void
    {
        $this->notifyUser(
            $user,
            self::CATEGORY_KK_PROFILING,
            'KK Profiling Approved',
            "Your KK Profiling registration for {$barangayName} has been approved. You can now access all SK OnePortal services.",
            route('dashboard'),
        );
    }

    /**
     * @return Builder<KabataanNotification>
     */
    private function forUserQuery(User $user)
    {
        return KabataanNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');
    }

    /**
     * @return array{id: int, category: string, category_label: string, title: string, text: string, time: string, unread: bool, action_url: string|null}
     */
    private function formatNotification(KabataanNotification $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'category' => $notification->category,
            'category_label' => $this->categoryLabel($notification->category),
            'title' => $this->stripEmoji($notification->title),
            'text' => $this->stripEmoji($notification->body),
            'time' => $this->formatTime($notification->created_at),
            'unread' => $notification->isUnread(),
            'action_url' => $notification->action_url,
        ];
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            self::CATEGORY_KK_PROFILING => 'KK Profiling',
            self::CATEGORY_PROGRAM => 'Program',
            self::CATEGORY_REMINDER => 'Reminder',
            self::CATEGORY_ANNOUNCEMENT => 'Announcement',
            default => 'General',
        };
    }

    private function formatTime(?Carbon $timestamp): string
    {
        if (! $timestamp) {
            return '—';
        }

        return $timestamp->format('g:i A').' · '.$timestamp->format('M j, Y');
    }

    private function stripEmoji(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $cleaned = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);
    }

    private static ?bool $tableExistsCache = null;

    private function tableExists(): bool
    {
        if (self::$tableExistsCache === null) {
            self::$tableExistsCache = Schema::hasTable('kabataan_notifications');
        }

        return self::$tableExistsCache;
    }
}
