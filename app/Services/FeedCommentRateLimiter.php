<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Burst rate limiter shared by Community Feed comments and chat messages.
 * 3 actions inside WINDOW_SECONDS → COOLDOWN_SECONDS lockout.
 */
class FeedCommentRateLimiter
{
    /** Max actions allowed inside the spam window before cooldown. */
    public const BURST_LIMIT = 3;

    /** Seconds without a new action before the burst count resets. */
    public const WINDOW_SECONDS = 60;

    /** Cooldown after reaching BURST_LIMIT actions inside the window. */
    public const COOLDOWN_SECONDS = 60;

    public const MAX_BODY_LENGTH = 2000;

    public const SCOPE_COMMENT = 'feed_comment';

    public const SCOPE_CHAT = 'chat_message';

    /**
     * @return array{
     *     allowed: bool,
     *     retry_after: int|null,
     *     message: string|null,
     *     burst_used: int,
     *     burst_remaining: int,
     *     locked: bool
     * }
     */
    public function check(string $userType, int $userId, string $scope = self::SCOPE_COMMENT): array
    {
        return $this->status($this->readState($userType, $userId, $scope), $scope);
    }

    /**
     * Record a successful action. Locks for COOLDOWN_SECONDS after the 3rd
     * action inside WINDOW_SECONDS. Waiting longer than WINDOW_SECONDS since
     * the last action resets the burst count.
     *
     * @return array{
     *     allowed: bool,
     *     retry_after: int|null,
     *     message: string|null,
     *     burst_used: int,
     *     burst_remaining: int,
     *     locked: bool
     * }
     */
    public function hit(string $userType, int $userId, string $scope = self::SCOPE_COMMENT): array
    {
        $now = time();
        $state = $this->readState($userType, $userId, $scope);

        if (($state['locked_until'] ?? 0) > $now) {
            return $this->status($state, $scope);
        }

        $count = (int) ($state['count'] ?? 0);
        $lastAt = (int) ($state['last_at'] ?? 0);

        if ($count > 0 && ($now - $lastAt) > self::WINDOW_SECONDS) {
            $count = 0;
        }

        $count++;

        if ($count >= self::BURST_LIMIT) {
            $state = [
                'count' => 0,
                'last_at' => $now,
                'locked_until' => $now + self::COOLDOWN_SECONDS,
            ];
        } else {
            $state = [
                'count' => $count,
                'last_at' => $now,
                'locked_until' => 0,
            ];
        }

        $this->writeState($userType, $userId, $state, $scope);

        $status = $this->status($state, $scope);
        if ($status['locked']) {
            $label = $this->actionLabel($scope, true);
            $status['message'] = ucfirst($label).' limit reached (3 '.$label.'s). Please wait '
                .$status['retry_after'].' second(s) before '.$this->actionVerb($scope).' again.';
            $status['burst_used'] = self::BURST_LIMIT;
            $status['burst_remaining'] = 0;
        }

        return $status;
    }

    /**
     * @param  array{count?: int, last_at?: int, locked_until?: int}  $state
     * @return array{
     *     allowed: bool,
     *     retry_after: int|null,
     *     message: string|null,
     *     burst_used: int,
     *     burst_remaining: int,
     *     locked: bool
     * }
     */
    private function status(array $state, string $scope = self::SCOPE_COMMENT): array
    {
        $now = time();
        $lockedUntil = (int) ($state['locked_until'] ?? 0);

        if ($lockedUntil > $now) {
            $retryAfter = max(1, $lockedUntil - $now);
            $label = $this->actionLabel($scope, true);

            return [
                'allowed' => false,
                'retry_after' => $retryAfter,
                'message' => 'Too many '.$label.'s. Please wait '.$retryAfter.' second(s) before '.$this->actionVerb($scope).' again.',
                'burst_used' => self::BURST_LIMIT,
                'burst_remaining' => 0,
                'locked' => true,
            ];
        }

        $count = (int) ($state['count'] ?? 0);
        $lastAt = (int) ($state['last_at'] ?? 0);

        if ($count > 0 && ($now - $lastAt) > self::WINDOW_SECONDS) {
            $count = 0;
        }

        return [
            'allowed' => true,
            'retry_after' => null,
            'message' => null,
            'burst_used' => $count,
            'burst_remaining' => max(0, self::BURST_LIMIT - $count),
            'locked' => false,
        ];
    }

    /**
     * @return array{count: int, last_at: int, locked_until: int}
     */
    private function readState(string $userType, int $userId, string $scope): array
    {
        $raw = Cache::get($this->cacheKey($userType, $userId, $scope));

        if (! is_array($raw)) {
            return ['count' => 0, 'last_at' => 0, 'locked_until' => 0];
        }

        return [
            'count' => (int) ($raw['count'] ?? 0),
            'last_at' => (int) ($raw['last_at'] ?? 0),
            'locked_until' => (int) ($raw['locked_until'] ?? 0),
        ];
    }

    /**
     * @param  array{count: int, last_at: int, locked_until: int}  $state
     */
    private function writeState(string $userType, int $userId, array $state, string $scope): void
    {
        $ttl = self::WINDOW_SECONDS + self::COOLDOWN_SECONDS + 5;
        Cache::put($this->cacheKey($userType, $userId, $scope), $state, $ttl);
    }

    private function cacheKey(string $userType, int $userId, string $scope): string
    {
        $bucket = $scope === self::SCOPE_CHAT ? self::SCOPE_CHAT : self::SCOPE_COMMENT;

        return $bucket.'_burst:'.$userType.':'.$userId;
    }

    private function actionLabel(string $scope, bool $pluralBase = false): string
    {
        if ($scope === self::SCOPE_CHAT) {
            return $pluralBase ? 'message' : 'message';
        }

        return 'comment';
    }

    private function actionVerb(string $scope): string
    {
        return $scope === self::SCOPE_CHAT ? 'messaging' : 'commenting';
    }
}
