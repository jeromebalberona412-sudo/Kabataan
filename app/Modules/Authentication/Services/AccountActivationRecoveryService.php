<?php

namespace App\Modules\Authentication\Services;

use App\Models\KabataanRegistration;
use App\Models\User;
use App\Modules\Authentication\Notifications\KabataanAccountActivationNotification;
use App\Services\KabataanAuthService;
use App\Services\KkProfilingAccountInviteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AccountActivationRecoveryService
{
    public const OUTCOME_SENT = 'sent';

    public const OUTCOME_INVALID = 'invalid';

    public const OUTCOME_ALREADY_ACTIVE = 'already_active';

    public const OUTCOME_THROTTLED = 'throttled';

    public const SESSION_EMAIL_KEY = 'account_activation_email';

    public const SENT_MESSAGE = 'Your activation link has been sent. Please check your inbox and spam/junk folder.';

    public const INVALID_MESSAGE = 'Invalid email or no email.';

    public const ALREADY_ACTIVE_MESSAGE = 'This account has already been activated. Please sign in or use Forgot Password if you cannot access your account.';

    public const THROTTLED_MESSAGE = 'Please wait before requesting another activation email.';

    public function __construct(
        protected KkProfilingAccountInviteService $inviteService,
        protected KabataanAuthService $kabataanAuthService,
    ) {}

    public function cooldownSeconds(): int
    {
        return max(1, (int) config('kabataan_auth.account_activation.cooldown_seconds', 60));
    }

    public function cooldownRemaining(string $email): int
    {
        $expiresAt = (int) Cache::get($this->cooldownKey(hash('sha256', Str::lower(trim($email)))), 0);

        if ($expiresAt <= 0) {
            return 0;
        }

        return max(0, $expiresAt - time());
    }

    public function requestNewLink(Request $request, string $email): string
    {
        $normalizedEmail = Str::lower(trim($email));
        $emailHash = hash('sha256', $normalizedEmail);

        if ($this->cooldownRemaining($normalizedEmail) > 0) {
            return self::OUTCOME_THROTTLED;
        }

        $lock = Cache::lock('kabataan-activation-send:'.$emailHash, 15);

        if (! $lock->get()) {
            return self::OUTCOME_THROTTLED;
        }

        try {
            if ($this->kabataanAuthService->isBlockedEmail($normalizedEmail)) {
                return self::OUTCOME_INVALID;
            }

            $registration = $this->findRegistrationByEmail($normalizedEmail);

            if ($registration === null) {
                $user = $this->findUserByEmail($normalizedEmail);
                if ($user !== null && $this->isActivatedUser($user)) {
                    return self::OUTCOME_ALREADY_ACTIVE;
                }

                return self::OUTCOME_INVALID;
            }

            if ($this->isAlreadyActivated($registration)) {
                return self::OUTCOME_ALREADY_ACTIVE;
            }

            if (! $this->isEligible($registration)) {
                return self::OUTCOME_INVALID;
            }

            $plainToken = $this->inviteService->issueInviteToken($registration);

            try {
                Notification::route('mail', $normalizedEmail)
                    ->notify(new KabataanAccountActivationNotification(
                        $registration->full_name,
                        $this->inviteService->activationUrl($registration, $plainToken),
                    ));
                $this->inviteService->markInviteSent($registration->fresh() ?? $registration);
            } catch (\Throwable $exception) {
                report($exception);
            }

            $this->markCooldown($emailHash);

            return self::OUTCOME_SENT;
        } finally {
            $lock->release();
        }
    }

    protected function findRegistrationByEmail(string $normalizedEmail): ?KabataanRegistration
    {
        return KabataanRegistration::query()
            ->select([
                'id',
                'tenant_id',
                'barangay_id',
                'user_id',
                'first_name',
                'last_name',
                'middle_name',
                'suffix',
                'email',
                'form_data',
                'status',
                'password_set_at',
                'archived_at',
            ])
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->latest('id')
            ->first();
    }

    protected function findUserByEmail(string $normalizedEmail): ?User
    {
        return User::query()
            ->select(['id', 'email', 'role', 'status', 'email_verified_at'])
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
    }

    protected function isAlreadyActivated(KabataanRegistration $registration): bool
    {
        if ($registration->password_set_at !== null && $registration->user_id) {
            $user = User::query()->select(['id', 'email', 'role', 'status'])->find($registration->user_id);

            if ($user !== null && (string) $user->status !== User::STATUS_INACTIVE) {
                return true;
            }
        }

        if (in_array((string) $registration->status, ['password_set', 'active'], true) && $registration->user_id) {
            $user = User::query()->select(['id', 'status'])->find($registration->user_id);

            return $user !== null && (string) $user->status !== User::STATUS_INACTIVE;
        }

        return false;
    }

    protected function isEligible(KabataanRegistration $registration): bool
    {
        if (trim((string) $registration->email) === '') {
            return false;
        }

        if ((string) $registration->status === 'rejected') {
            return false;
        }

        if ($registration->archived_at !== null) {
            return false;
        }

        if ($this->isAlreadyActivated($registration)) {
            return false;
        }

        $user = $registration->user_id
            ? User::query()->select(['id', 'email', 'role', 'status'])->find($registration->user_id)
            : $this->findUserByEmail(Str::lower(trim((string) $registration->email)));

        if ($user !== null && (string) $user->status === User::STATUS_INACTIVE) {
            return false;
        }

        return true;
    }

    protected function isActivatedUser(User $user): bool
    {
        if (! $this->kabataanAuthService->canAccessPortal($user)) {
            return false;
        }

        return in_array((string) $user->status, [User::STATUS_ACTIVE, User::STATUS_PENDING_APPROVAL], true)
            && $user->email_verified_at !== null;
    }

    protected function cooldownKey(string $emailHash): string
    {
        return 'kabataan-activation-cooldown:'.$emailHash;
    }

    protected function markCooldown(string $emailHash): void
    {
        $seconds = $this->cooldownSeconds();
        Cache::put($this->cooldownKey($emailHash), time() + $seconds, $seconds);
    }
}
