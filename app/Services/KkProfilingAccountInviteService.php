<?php

namespace App\Services;

use App\Models\KabataanRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KkProfilingAccountInviteService
{
    public const TOKEN_TTL_HOURS = 24;

    public const FORM_TOKEN_KEY = 'account_invite_token_hash';

    public const FORM_EXPIRES_KEY = 'account_invite_expires_at';

    public const FORM_USED_KEY = 'account_invite_used_at';

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function findValidRegistration(int $registrationId, string $plainToken): KabataanRegistration
    {
        $registration = KabataanRegistration::query()->find($registrationId);

        if (! $registration) {
            throw ValidationException::withMessages([
                'token' => ['This activation link is invalid.'],
            ]);
        }

        $formData = is_array($registration->form_data) ? $registration->form_data : [];
        $storedHash = (string) ($formData[self::FORM_TOKEN_KEY] ?? '');
        $expiresAt = (string) ($formData[self::FORM_EXPIRES_KEY] ?? '');
        $usedAt = $formData[self::FORM_USED_KEY] ?? null;

        if ($storedHash === '' || ! hash_equals($storedHash, self::hashToken($plainToken))) {
            throw ValidationException::withMessages([
                'token' => ['This activation link is no longer valid.'],
            ]);
        }

        if ($usedAt) {
            throw ValidationException::withMessages([
                'token' => ['This activation link is no longer valid.'],
            ]);
        }

        if ($expiresAt === '' || now()->greaterThan($expiresAt)) {
            throw ValidationException::withMessages([
                'token' => ['This activation link has expired.'],
            ]);
        }

        $email = strtolower(trim((string) $registration->email));

        if ($email === '') {
            throw ValidationException::withMessages([
                'token' => ['This KK Profiling record does not have an email address yet.'],
            ]);
        }

        return $registration;
    }

    public function activate(KabataanRegistration $registration, string $plainToken, string $password): User
    {
        $this->findValidRegistration((int) $registration->id, $plainToken);

        $email = strtolower(trim((string) $registration->email));

        return DB::transaction(function () use ($registration, $email, $password) {
            $registration = KabataanRegistration::query()
                ->where('id', $registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $formData = is_array($registration->form_data) ? $registration->form_data : [];

            if (! empty($formData[self::FORM_USED_KEY])) {
                throw ValidationException::withMessages([
                    'token' => ['This activation link has already been used.'],
                ]);
            }

            $alreadyApproved = in_array($registration->evaluation_status, ['active', 'Auto Approved', 'ID Verified'], true);
            $linkedUser = $registration->user_id
                ? User::query()->where('id', $registration->user_id)->first()
                : null;
            $existingUser = User::query()->where('email', $email)->first();

            if ($existingUser && (! $linkedUser || (int) $existingUser->id !== (int) $linkedUser->id)) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered to another account.'],
                ]);
            }

            if ($linkedUser) {
                $linkedUser->forceFill([
                    'name' => $registration->full_name,
                    'email' => $email,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'tenant_id' => $registration->tenant_id,
                    'barangay_id' => $registration->barangay_id,
                    'role' => User::ROLE_KABATAAN,
                    'status' => $alreadyApproved ? User::STATUS_ACTIVE : User::STATUS_PENDING_APPROVAL,
                ])->save();
                $user = $linkedUser;
            } else {
                $user = User::create([
                    'name' => $registration->full_name,
                    'email' => $email,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'tenant_id' => $registration->tenant_id,
                    'barangay_id' => $registration->barangay_id,
                    'role' => User::ROLE_KABATAAN,
                    'status' => $alreadyApproved ? User::STATUS_ACTIVE : User::STATUS_PENDING_APPROVAL,
                ]);
            }

            $formData['email'] = $email;
            $formData[self::FORM_USED_KEY] = now()->toIso8601String();
            unset($formData[self::FORM_TOKEN_KEY], $formData[self::FORM_EXPIRES_KEY]);

            $registration->update([
                'user_id' => $user->id,
                'email' => $email,
                'email_verified_at' => now(),
                'password_set_at' => now(),
                'status' => $alreadyApproved ? 'active' : 'password_set',
                'form_data' => $formData,
            ]);

            try {
                (new KkSurveyResponseService)->syncFromRegistration($registration->fresh(), $alreadyApproved ? 'approved' : 'pending');
            } catch (\Throwable $e) {
                report($e);
            }

            return $user;
        });
    }

    public function tokenTtlMinutes(): int
    {
        return max(1, (int) config('kabataan_auth.account_activation.expire_minutes', self::TOKEN_TTL_HOURS * 60));
    }

    public function issueInviteToken(KabataanRegistration $registration): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $formData = is_array($registration->form_data) ? $registration->form_data : [];
        $formData[self::FORM_TOKEN_KEY] = self::hashToken($plainToken);
        $formData[self::FORM_EXPIRES_KEY] = now()->addMinutes($this->tokenTtlMinutes())->toIso8601String();
        unset($formData[self::FORM_USED_KEY], $formData['account_invite_sent_at']);

        $registration->update(['form_data' => $formData]);

        return $plainToken;
    }

    public function markInviteSent(KabataanRegistration $registration): void
    {
        $formData = is_array($registration->form_data) ? $registration->form_data : [];
        $formData['account_invite_sent_at'] = now()->toIso8601String();
        $registration->update(['form_data' => $formData]);
    }

    public function activationUrl(KabataanRegistration $registration, string $plainToken): string
    {
        return url('/kkprofiling/account-invite/'.$registration->id.'/'.$plainToken);
    }

    public function errorTypeFromMessage(string $message): string
    {
        $normalized = strtolower($message);

        if (str_contains($normalized, 'expired')) {
            return 'expired';
        }

        return 'invalid';
    }
}
