<?php

namespace App\Modules\Authentication\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    public const REMEMBER_COOKIE_NAME = 'kabataan_remember_device';

    protected function tableName(): string
    {
        return 'kabataan_trusted_devices';
    }

    public function isTrusted(User $user, Request $request): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        return $this->matchesRememberCookie($user, $request);
    }

    public function rememberDevice(User $user, Request $request, ?string $loginFingerprint = null): void
    {
        if (! $this->hasTable()) {
            return;
        }

        $plainToken = Str::random(64);
        $expirationDays = $this->expirationDays();
        $expiresAt = now()->addDays($expirationDays);
        $currentFingerprint = app(DeviceFingerprintService::class)->fingerprint($request);

        $this->persistRememberDevice($user, $request, $currentFingerprint, $plainToken, $expiresAt);

        if (
            $loginFingerprint !== null
            && $loginFingerprint !== ''
            && $loginFingerprint !== $currentFingerprint
        ) {
            $this->persistRememberDevice($user, $request, $loginFingerprint, $plainToken, $expiresAt);
        }

        Cookie::queue($this->makeRememberCookie($user, $plainToken, $expiresAt));
    }

    public function refreshRememberCookieIfPresent(User $user, Request $request): void
    {
        if (! $this->hasTable() || ! $this->hasColumn('device_token_hash')) {
            return;
        }

        $cookieValue = (string) $request->cookie(self::REMEMBER_COOKIE_NAME, '');

        if ($cookieValue === '' || ! str_contains($cookieValue, '|')) {
            return;
        }

        [$cookieUserId, $plainToken] = explode('|', $cookieValue, 2);
        $plainToken = trim($plainToken);

        if ((int) $cookieUserId !== (int) $user->getKey() || $plainToken === '') {
            return;
        }

        $tokenHash = hash('sha256', $plainToken);
        $record = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('device_token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            return;
        }

        $expiresAt = now()->addDays($this->expirationDays());
        $fingerprint = app(DeviceFingerprintService::class)->fingerprint($request);

        $this->syncTrustedDeviceRecord($user, $request, $record, $fingerprint, $expiresAt);

        Cookie::queue($this->makeRememberCookie($user, $plainToken, $expiresAt));
    }

    public function revoke(User $user, Request $request): void
    {
        if (! $this->hasTable()) {
            return;
        }

        $fingerprint = app(DeviceFingerprintService::class)->fingerprint($request);

        DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->delete();
    }

    public function revokeCurrentDevice(User $user, Request $request): void
    {
        $this->revokeByCookie($user, $request);
        $this->revoke($user, $request);
        $this->forgetRememberCookie();
    }

    public function forgetRememberCookie(): void
    {
        Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE_NAME));
    }

    public function revokeAllForUser(User $user): void
    {
        if (! $this->hasTable()) {
            return;
        }

        DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->delete();

        $this->forgetRememberCookie();
    }

    public function expirationDays(): int
    {
        return max(1, (int) config('kabataan_auth.remember.lifetime_days', 7));
    }

    public function rememberLifetimeMinutes(): int
    {
        return $this->expirationDays() * 24 * 60;
    }

    protected function revokeByCookie(User $user, Request $request): void
    {
        if (! $this->hasTable() || ! $this->hasColumn('device_token_hash')) {
            return;
        }

        $cookieValue = (string) $request->cookie(self::REMEMBER_COOKIE_NAME, '');

        if ($cookieValue === '' || ! str_contains($cookieValue, '|')) {
            return;
        }

        [, $plainToken] = explode('|', $cookieValue, 2);
        $plainToken = trim($plainToken);

        if ($plainToken === '') {
            return;
        }

        DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('device_token_hash', hash('sha256', $plainToken))
            ->delete();
    }

    protected function matchesRememberCookie(User $user, Request $request): bool
    {
        if (! $this->hasColumn('device_token_hash')) {
            return false;
        }

        $cookieValue = (string) $request->cookie(self::REMEMBER_COOKIE_NAME, '');

        if ($cookieValue === '' || ! str_contains($cookieValue, '|')) {
            return false;
        }

        [$cookieUserId, $plainToken] = explode('|', $cookieValue, 2);
        $cookieUserId = (int) $cookieUserId;
        $plainToken = trim($plainToken);

        if ($cookieUserId !== (int) $user->getKey() || $plainToken === '') {
            return false;
        }

        $tokenHash = hash('sha256', $plainToken);
        $record = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('device_token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            return false;
        }

        $fingerprint = app(DeviceFingerprintService::class)->fingerprint($request);
        $this->syncTrustedDeviceRecord($user, $request, $record, $fingerprint);

        return true;
    }

    /**
     * @param  object  $record
     */
    protected function syncTrustedDeviceRecord(
        User $user,
        Request $request,
        object $record,
        string $fingerprint,
        $expiresAt = null,
    ): void {
        $updates = [
            'last_used_at' => now(),
            'ip_address' => (string) $request->ip(),
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'updated_at' => now(),
        ];

        if ($expiresAt !== null) {
            $updates['expires_at'] = $expiresAt;
        }

        if ((string) ($record->fingerprint ?? '') === $fingerprint) {
            DB::table($this->tableName())
                ->where('id', $record->id)
                ->update($updates);

            return;
        }

        $existingByFingerprint = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->where('id', '!=', $record->id)
            ->first();

        if ($existingByFingerprint !== null) {
            $mergeUpdates = $updates;

            if ($this->hasColumn('device_token_hash') && ! empty($record->device_token_hash)) {
                $mergeUpdates['device_token_hash'] = $record->device_token_hash;
            }

            DB::table($this->tableName())
                ->where('id', $existingByFingerprint->id)
                ->update($mergeUpdates);

            DB::table($this->tableName())
                ->where('id', $record->id)
                ->delete();

            return;
        }

        DB::table($this->tableName())
            ->where('id', $record->id)
            ->update(array_merge($updates, [
                'fingerprint' => $fingerprint,
            ]));
    }

    protected function persistRememberDevice(
        User $user,
        Request $request,
        string $fingerprint,
        string $plainToken,
        $expiresAt,
    ): void {
        $payload = [
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'ip_address' => (string) $request->ip(),
            'expires_at' => $expiresAt,
            'last_used_at' => now(),
            'updated_at' => now(),
            'metadata' => json_encode([
                'source' => 'remember_me',
                'cookie_name' => self::REMEMBER_COOKIE_NAME,
            ]),
        ];

        if ($this->hasColumn('device_token_hash')) {
            $payload['device_token_hash'] = hash('sha256', $plainToken);
        }

        $existing = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing === null) {
            DB::table($this->tableName())->insert(array_merge($payload, [
                'user_id' => $user->getKey(),
                'fingerprint' => $fingerprint,
                'created_at' => now(),
            ]));
        } else {
            DB::table($this->tableName())
                ->where('id', $existing->id)
                ->update($payload);
        }
    }

    protected function makeRememberCookie(User $user, string $plainToken, $expiresAt)
    {
        $minutes = max(1, now()->diffInMinutes($expiresAt, false));

        return cookie(
            name: self::REMEMBER_COOKIE_NAME,
            value: $user->getKey().'|'.$plainToken,
            minutes: $minutes,
            path: '/',
            domain: null,
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    protected function hasColumn(string $column): bool
    {
        $table = $this->tableName();

        return (bool) Cache::rememberForever("schema_col:{$table}.{$column}", function () use ($table, $column) {
            try {
                return Schema::hasColumn($table, $column);
            } catch (\Throwable) {
                return false;
            }
        });
    }

    protected function hasTable(): bool
    {
        $table = $this->tableName();

        return (bool) Cache::rememberForever("schema_tbl:{$table}", function () use ($table) {
            try {
                return Schema::hasTable($table);
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
