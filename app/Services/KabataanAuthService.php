<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class KabataanAuthService
{
    public const SIGNIN_DENIED_MESSAGE = 'Invalid Email or Password';
    private const CACHE_TTL = 3600; // 1 hour

    public function canAccessPortal(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isBlockedEmail((string) $user->email)) {
            return false;
        }

        return $this->hasAllowedRole($user);
    }

    public function hasAllowedRole(User $user): bool
    {
        $allowedRoles = $this->allowedRoles();

        if ($allowedRoles === []) {
            return false;
        }

        $role = strtolower(trim((string) $user->role));

        return in_array($role, $allowedRoles, true);
    }

    public function isBlockedEmail(string $email): bool
    {
        $normalized = Str::lower(trim($email));

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $this->blockedEmails(), true);
    }

    /**
     * @return list<string>
     */
    public function allowedRoles(): array
    {
        return Cache::remember('kabataan_auth.allowed_roles', self::CACHE_TTL, function () {
            $roles = config('kabataan_auth.allowed_roles', ['kabataan', 'user']);

            return array_values(array_unique(array_map(
                static fn (string $role): string => strtolower(trim($role)),
                is_array($roles) ? $roles : []
            )));
        });
    }

    /**
     * @return list<string>
     */
    public function blockedEmails(): array
    {
        return Cache::remember('kabataan_auth.blocked_emails', self::CACHE_TTL, function () {
            $emails = config('kabataan_auth.blocked_emails', ['skoneportal@gmail.com']);

            return array_values(array_unique(array_map(
                static fn (string $email): string => Str::lower(trim($email)),
                is_array($emails) ? $emails : []
            )));
        });
    }
}
