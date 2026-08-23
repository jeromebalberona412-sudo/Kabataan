<?php

namespace App\Modules\Profile\Services;

use App\Models\User;
use App\Modules\Profile\Notifications\EmailChangeVerificationNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailChangeService
{
    private const TOKEN_TTL_MINUTES = 60;

    private const SET_PASSWORD_TOKEN_TTL_HOURS = 2;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const COMPLETED_CACHE_TTL_MINUTES = 30;

    public function hasPendingChange(User $user): bool
    {
        return filled($user->pending_email)
            && filled($user->email_change_token)
            && blank($user->email_change_verified_at);
    }

    public function requestChange(User $user, string $currentEmail, string $newEmail, string $password): void
    {
        $currentEmail = strtolower(trim($currentEmail));
        $newEmail = strtolower(trim($newEmail));

        if (strtolower((string) $user->email) !== $currentEmail) {
            throw ValidationException::withMessages([
                'current_email' => ['Current email does not match your account.'],
            ]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password you entered is incorrect.'],
            ]);
        }

        if ($newEmail === $currentEmail) {
            throw ValidationException::withMessages([
                'new_email' => ['New email must be different from your current email.'],
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'new_email' => ['This email address is already in use.'],
            ]);
        }

        if (User::query()->whereRaw('LOWER(pending_email) = ?', [$newEmail])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'new_email' => ['This email address is already pending verification on another account.'],
            ]);
        }

        $this->forgetRecentlyCompleted($user->getKey());

        $plainToken = Str::random(64);

        $user->forceFill([
            'pending_email' => $newEmail,
            'email_change_token' => hash('sha256', $plainToken),
            'email_change_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'email_change_verified_at' => null,
            'email_change_last_sent_at' => now(),
        ])->save();

        Notification::route('mail', $user->pending_email)
            ->notify(new EmailChangeVerificationNotification($user, $plainToken));
    }

    public function resend(User $user): void
    {
        if (! $this->hasPendingChange($user)) {
            throw ValidationException::withMessages([
                'email' => ['No pending email change request found.'],
            ]);
        }

        if ($user->email_change_last_sent_at?->isAfter(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            $seconds = max(1, self::RESEND_COOLDOWN_SECONDS - (int) $user->email_change_last_sent_at->diffInSeconds(now()));
            throw ValidationException::withMessages([
                'email' => ["Please wait {$seconds} seconds before resending."],
            ]);
        }

        $plainToken = Str::random(64);

        $user->forceFill([
            'email_change_token' => hash('sha256', $plainToken),
            'email_change_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            'email_change_last_sent_at' => now(),
        ])->save();

        Notification::route('mail', $user->pending_email)
            ->notify(new EmailChangeVerificationNotification($user, $plainToken));
    }

    public function cancel(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'email_change_token' => null,
            'email_change_token_expires_at' => null,
            'email_change_verified_at' => null,
            'email_change_last_sent_at' => null,
        ])->save();

        User::query()
            ->whereKey($user->id)
            ->update(['must_change_password' => DB::raw("'false'::boolean")]);

        $this->forgetRecentlyCompleted($user->getKey());
    }

    /**
     * @return array{user: User, set_password_token: string}
     */
    public function confirm(int $userId, string $plainToken): array
    {
        $user = User::query()->find($userId);

        if ($user === null || ! $this->hasPendingChange($user)) {
            throw ValidationException::withMessages([
                'token' => ['This email change link is invalid or has already been used.'],
            ]);
        }

        if (! hash_equals((string) $user->email_change_token, hash('sha256', $plainToken))) {
            throw ValidationException::withMessages([
                'token' => ['This email change link is invalid.'],
            ]);
        }

        if ($user->email_change_token_expires_at === null || $user->email_change_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This email change link has expired. Please request a new one.'],
            ]);
        }

        $newEmail = strtolower((string) $user->pending_email);

        if (User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email address is no longer available. Please start a new request.'],
            ]);
        }

        $setPasswordToken = Str::random(64);

        $user->forceFill([
            'email_change_verified_at' => now(),
            'email_change_last_sent_at' => null,
            'email_change_token' => hash('sha256', $setPasswordToken),
            'email_change_token_expires_at' => now()->addHours(self::SET_PASSWORD_TOKEN_TTL_HOURS),
        ])->save();

        User::query()
            ->whereKey($user->id)
            ->update(['must_change_password' => DB::raw("'true'::boolean")]);

        return [
            'user' => $user->fresh(),
            'set_password_token' => $setPasswordToken,
        ];
    }

    public function markRecentlyCompleted(int $userId): void
    {
        Cache::put(
            $this->completedCacheKey($userId),
            true,
            now()->addMinutes(self::COMPLETED_CACHE_TTL_MINUTES),
        );
    }

    public function wasRecentlyCompleted(int $userId): bool
    {
        return (bool) Cache::get($this->completedCacheKey($userId), false);
    }

    public function forgetRecentlyCompleted(int $userId): void
    {
        Cache::forget($this->completedCacheKey($userId));
    }

    protected function completedCacheKey(int $userId): string
    {
        return "kabataan_email_change_completed:{$userId}";
    }

    public function hasPendingPasswordSet(User $user): bool
    {
        return filled($user->pending_email)
            && filled($user->email_change_verified_at)
            && filled($user->email_change_token)
            && (bool) $user->must_change_password;
    }

    public function validateSetPasswordToken(int $userId, string $plainToken): User
    {
        $user = User::query()->find($userId);

        if ($user === null || ! $this->hasPendingPasswordSet($user)) {
            throw ValidationException::withMessages([
                'token' => ['This password setup link is invalid or has already been used.'],
            ]);
        }

        if (! hash_equals((string) $user->email_change_token, hash('sha256', $plainToken))) {
            throw ValidationException::withMessages([
                'token' => ['This password setup link is invalid.'],
            ]);
        }

        if ($user->email_change_token_expires_at === null || $user->email_change_token_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This password setup link has expired. Please sign in and contact support if you need help.'],
            ]);
        }

        return $user;
    }

    public function completePasswordSet(User $user, string $plainPassword): void
    {
        $newEmail = strtolower((string) $user->pending_email);

        if (blank($newEmail)) {
            throw ValidationException::withMessages([
                'password' => ['No pending email change was found. Please start a new request.'],
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$newEmail])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'password' => ['This email address is no longer available. Please start a new request.'],
            ]);
        }

        $user->forceFill([
            'email' => $newEmail,
            'pending_email' => null,
            'password' => Hash::make($plainPassword),
            'email_verified_at' => now(),
            'remember_token' => null,
            'email_change_token' => null,
            'email_change_token_expires_at' => null,
            'email_change_verified_at' => null,
            'email_change_last_sent_at' => null,
        ])->save();

        app(\App\Modules\Authentication\Services\TrustedDeviceService::class)
            ->revokeAllForUser($user);

        User::query()
            ->whereKey($user->id)
            ->update(['must_change_password' => DB::raw("'false'::boolean")]);
    }

    public function resendCooldownRemaining(User $user): int
    {
        if ($user->email_change_last_sent_at === null) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (int) $user->email_change_last_sent_at->diffInSeconds(now()));
    }
}
