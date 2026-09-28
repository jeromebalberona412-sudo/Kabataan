<?php

namespace App\Modules\Guest_Kabataan\Services;

use App\Mail\KabataanSetPasswordMail;
use App\Rules\ValidEmailAddress;
use App\Models\GuestClaimLockout;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Services\BarangayZoneService;
use App\Services\DuplicateKabataanRegistrationService;
use App\Services\KabataanSecurityQuestionService;
use App\Services\KkRegistrationDraftService;
use App\Services\InvalidEmailService;
use App\Services\TurnstileService;
use App\Support\MailUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class GuestKabataanClaimService
{
    public const COOKIE = 'guest_claim_device';

    public const SESSION_KEY = 'guest_kabataan_claim';

    public const MAX_ATTEMPTS = 3;

    public const LOCK_MINUTES = 3;

    public const PENDING_KEY = 'guest_kabataan_identity';

    public function __construct(
        private readonly DuplicateKabataanRegistrationService $identities,
        private readonly KabataanSecurityQuestionService $securityQuestions,
        private readonly KkRegistrationDraftService $drafts,
        private readonly BarangayZoneService $zones,
        private readonly TurnstileService $turnstile,
        private readonly InvalidEmailService $invalidEmails,
    ) {}

    /**
     * @return array{locked: bool, remaining_seconds: int, attempts_left: int}
     */
    public function lockStatus(Request $request): array
    {
        $this->rememberDevice($request);
        $row = $this->lockout($request);
        if ($row === null) {
            return $this->statusPayload(false, 0, self::MAX_ATTEMPTS);
        }

        $remaining = $this->remainingLockSeconds($row);
        if ($remaining > 0) {
            return $this->statusPayload(true, $remaining, 0);
        }

        if ($row->locked_until !== null) {
            $row->forceFill([
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }

        return $this->statusPayload(false, 0, max(0, self::MAX_ATTEMPTS - (int) $row->failed_attempts));
    }

    public function forgetClaim(): void
    {
        session()->forget([self::SESSION_KEY, self::PENDING_KEY]);
    }

    /**
     * Personal information only. A match is stored until the security questions pass.
     *
     * @param  array<string, mixed>  $input
     * @return array{status: string, registration: ?KabataanRegistration}
     */
    public function confirmIdentity(Request $request, int $barangayId, array $input): array
    {
        $this->assertNotLocked($request);
        $fields = $this->validatedIdentity($barangayId, $input);
        $registration = $this->findByIdentity($barangayId, $fields);

        if ($registration === null) {
            return ['status' => 'not_found', 'registration' => null];
        }

        if ($registration->user_id) {
            session()->forget(self::PENDING_KEY);

            return ['status' => 'already_account', 'registration' => $registration];
        }

        session([
            self::PENDING_KEY => [
                'registration_id' => $registration->id,
                'barangay_id' => $barangayId,
                'verified_at' => now()->toIso8601String(),
            ],
        ]);

        return ['status' => 'ok', 'registration' => $registration];
    }

    /**
     * Security answers for the registration confirmed in this session.
     * Turnstile runs before the answers are compared.
     *
     * @param  array<string, mixed>  $input
     * @return array{status: string, registration: ?KabataanRegistration, locked: bool, remaining_seconds: int, attempts_left: int}
     */
    public function submitSecurity(Request $request, int $barangayId, array $input): array
    {
        $this->assertNotLocked($request);
        $this->requireTurnstile($request);
        $this->securityQuestions->prepare($input['security_questions'] ?? null);

        $registration = $this->pendingRegistration($barangayId);
        if ($registration === null) {
            throw ValidationException::withMessages([
                'claim' => ['Confirm your personal information again before answering the security questions.'],
            ]);
        }

        if (! $this->securityAnswersMatch($registration, $input['security_questions'] ?? [])) {
            $this->recordFailure($request);
            $after = $this->lockStatus($request);

            return [
                'status' => 'wrong',
                'registration' => null,
                'locked' => $after['locked'],
                'remaining_seconds' => $after['remaining_seconds'],
                'attempts_left' => $after['attempts_left'],
            ];
        }

        $this->clearFailures($request);
        session()->forget(self::PENDING_KEY);

        if ($registration->user_id) {
            return [
                'status' => 'already_account',
                'registration' => $registration,
                'locked' => false,
                'remaining_seconds' => 0,
                'attempts_left' => self::MAX_ATTEMPTS,
            ];
        }

        session([
            self::SESSION_KEY => [
                'registration_id' => $registration->id,
                'barangay_id' => $barangayId,
                'verified_at' => now()->toIso8601String(),
            ],
        ]);

        return [
            'status' => 'ok',
            'registration' => $registration,
            'locked' => false,
            'remaining_seconds' => 0,
            'attempts_left' => self::MAX_ATTEMPTS,
        ];
    }

    public function claimedRegistration(int $barangayId): ?KabataanRegistration
    {
        $stored = session(self::SESSION_KEY);
        $registrationId = is_array($stored) ? ($stored['registration_id'] ?? null) : null;
        if (! is_numeric($registrationId) || (int) ($stored['barangay_id'] ?? 0) !== $barangayId) {
            return null;
        }

        return KabataanRegistration::query()
            ->where('id', (int) $registrationId)
            ->where('barangay_id', $barangayId)
            ->whereNull('user_id')
            ->first();
    }

    public function sendActivationEmail(KabataanRegistration $registration, string $email): void
    {
        $email = strtolower(trim($email));
        validator(
            ['email' => $email],
            ['email' => ValidEmailAddress::profilingRules()],
            ValidEmailAddress::profilingMessages()
        )->validate();

        $alreadySaved = strcasecmp((string) $registration->email, $email) === 0;
        if (! $alreadySaved) {
            $this->assertEmailAvailableForClaim($registration, $email);
        }

        $previousEmail = $registration->email;
        $previousForm = $registration->form_data;
        $registration->email = $email;
        $form = is_array($registration->form_data) ? $registration->form_data : [];
        $form['email'] = $email;
        $registration->form_data = $form;
        $registration->save();

        try {
            $wizard = $this->drafts->beginGuestClaim($registration->fresh() ?? $registration, $email);
            $this->dispatchSetPasswordEmail($wizard, $email);
        } catch (\Throwable $e) {
            $registration->email = $previousEmail;
            $registration->form_data = $previousForm;
            $registration->save();
            throw $e;
        }
    }

    public function resendActivationEmail(KabataanRegistration $registration): void
    {
        $wizard = $this->drafts->resolveWizard();
        $email = strtolower(trim((string) ($registration->email ?? '')));
        if ($email === '' || ! is_array($wizard) || strtolower(trim((string) ($wizard['email'] ?? ''))) !== $email) {
            throw ValidationException::withMessages([
                'email' => ['Enter your email again to send a new set-password link.'],
            ]);
        }

        $this->drafts->assertSetPasswordEmailCooldown($wizard);
        $this->dispatchSetPasswordEmail($wizard, $email);
    }

    /**
     * @param  array<string, mixed>  $wizard
     */
    private function dispatchSetPasswordEmail(array $wizard, string $email): void
    {
        $linkToken = bin2hex(random_bytes(20));
        $setPasswordUrl = MailUrl::route('kkprofiling.wizard.set-password', [
            'token' => $wizard['token'],
            'hash' => $linkToken,
        ]);

        $this->invalidEmails->attemptMailDelivery($email, function () use ($email, $setPasswordUrl, $wizard) {
            Mail::to($email)->send(new KabataanSetPasswordMail(
                $setPasswordUrl,
                trim((string) ($wizard['step1_data']['first_name'] ?? '')),
            ));
        }, 'email');

        $this->drafts->applySetPasswordLink($wizard, $linkToken);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validatedIdentity(int $barangayId, array $input): array
    {
        $input['last_name'] = preg_replace('/\s+/', ' ', trim((string) ($input['last_name'] ?? ''))) ?: '';
        $input['first_name'] = preg_replace('/\s+/', ' ', trim((string) ($input['first_name'] ?? ''))) ?: '';
        $middle = preg_replace('/\s+/', ' ', trim((string) ($input['middle_name'] ?? ''))) ?: '';
        $input['middle_name'] = $middle === '' ? null : $middle;

        $validator = validator($input, [
            'last_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'first_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'middle_name' => ['nullable', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'suffix' => ['required', 'string', 'in:None,Jr.,Sr.,I,II,III,IV,V,Others'],
            'custom_suffix' => ['nullable', 'required_if:suffix,Others', 'string', 'max:5', 'regex:/^(?!\s+$)[A-Za-z.\s]+$/'],
            'purok_zone' => $this->zones->purokZoneRules($barangayId),
            'sex' => ['required', 'in:Male,Female'],
            'age' => ['required', 'integer', 'min:15', 'max:30'],
            'birthday' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'last_name.required' => 'Last Name is required.',
            'first_name.required' => 'First Name is required.',
            'last_name.min' => 'Minimum 2 characters required.',
            'first_name.min' => 'Minimum 2 characters required.',
            'middle_name.min' => 'Minimum 2 characters required.',
            'last_name.max' => '150 maximum characters only.',
            'first_name.max' => '150 maximum characters only.',
            'middle_name.max' => '150 maximum characters only.',
            'last_name.regex' => 'Letters, spaces, periods, and hyphens only.',
            'first_name.regex' => 'Letters, spaces, periods, and hyphens only.',
            'middle_name.regex' => 'Letters, spaces, periods, and hyphens only.',
            'suffix.required' => 'Please select a suffix.',
            'custom_suffix.required_if' => 'Please specify your suffix.',
            'custom_suffix.max' => 'Suffix must not exceed 5 characters.',
            'custom_suffix.regex' => 'Only text and valid Roman numeral suffixes are allowed.',
            'purok_zone.required' => 'Purok/Zone is required.',
            'purok_zone.exists' => 'Please select a purok or zone.',
            'sex.required' => 'Please select Sex Assigned by Birth.',
            'age.required' => 'Age is required.',
            'age.min' => 'Age must be 15 to 30 only.',
            'age.max' => 'Age must be 15 to 30 only.',
            'birthday.required' => 'Birthday is required.',
            'birthday.before_or_equal' => 'Birthday cannot be in the future.',
        ]);

        $fields = $validator->validate();
        if (($fields['suffix'] ?? null) === 'Others') {
            $customSuffix = trim((string) ($fields['custom_suffix'] ?? ''));
            $compact = strtoupper(str_replace(' ', '', $customSuffix));
            $validRoman = in_array($compact, ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'], true);
            $validText = (bool) preg_match('/^[A-Za-z.]+$/', str_replace(' ', '', $customSuffix));
            if ($customSuffix === '') {
                throw ValidationException::withMessages([
                    'custom_suffix' => ['Please specify your suffix.'],
                ]);
            }
            if ((! $validRoman && ! $validText) || strlen(str_replace(' ', '', $customSuffix)) > 5) {
                throw ValidationException::withMessages([
                    'custom_suffix' => ['Only text and valid Roman numeral suffixes are allowed.'],
                ]);
            }
        }

        try {
            $birthday = Carbon::parse($fields['birthday'], 'Asia/Manila')->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'birthday' => ['Invalid birthday value.'],
            ]);
        }

        if ($birthday->isFuture()) {
            throw ValidationException::withMessages([
                'birthday' => ['Birthday cannot be in the future.'],
            ]);
        }

        $computedAge = $birthday->age;
        if ($computedAge < 15) {
            throw ValidationException::withMessages([
                'birthday' => ['Age must be at least 15 years old.'],
            ]);
        }
        if ($computedAge > 30) {
            throw ValidationException::withMessages([
                'birthday' => ['Age must not exceed 30 years old.'],
            ]);
        }
        if ((int) $fields['age'] !== $computedAge) {
            throw ValidationException::withMessages([
                'birthday' => ['Birthday must match the selected age.'],
            ]);
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  mixed  $answers
     */
    private function pendingRegistration(int $barangayId): ?KabataanRegistration
    {
        $stored = session(self::PENDING_KEY);
        $registrationId = is_array($stored) ? ($stored['registration_id'] ?? null) : null;
        if (! is_numeric($registrationId) || (int) ($stored['barangay_id'] ?? 0) !== $barangayId) {
            return null;
        }

        return KabataanRegistration::query()
            ->with('securityQuestions')
            ->where('id', (int) $registrationId)
            ->where('barangay_id', $barangayId)
            ->whereNotIn('status', ['rejected'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function findByIdentity(int $barangayId, array $fields): ?KabataanRegistration
    {
        $last = mb_strtolower(trim((string) $fields['last_name']));
        $first = mb_strtolower(trim((string) $fields['first_name']));

        $candidates = KabataanRegistration::query()
            ->where('barangay_id', $barangayId)
            ->whereNotIn('status', ['rejected'])
            ->whereRaw('LOWER(last_name) = ?', [$last])
            ->whereRaw('LOWER(first_name) = ?', [$first])
            ->get();

        foreach ($candidates as $registration) {
            $form = is_array($registration->form_data) ? $registration->form_data : [];
            $suffix = trim((string) ($form['suffix'] ?? ''));
            $existing = array_merge($form, [
                'first_name' => $registration->first_name,
                'middle_name' => $registration->middle_name,
                'last_name' => $registration->last_name,
                'suffix' => $suffix !== '' ? $suffix : $registration->suffix,
                'birthday' => $form['birthday'] ?? null,
                'purok_zone' => $form['purok_zone'] ?? null,
                'sex' => $form['sex'] ?? null,
                'age' => $form['age'] ?? null,
            ]);

            if (! $this->identities->identitiesMatch($barangayId, $fields, $existing)) {
                continue;
            }

            if (strcasecmp(trim((string) ($form['purok_zone'] ?? '')), trim((string) $fields['purok_zone'])) !== 0) {
                continue;
            }

            if (strcasecmp(trim((string) ($form['sex'] ?? '')), trim((string) $fields['sex'])) !== 0) {
                continue;
            }

            return $registration;
        }

        return null;
    }

    private function securityAnswersMatch(KabataanRegistration $registration, mixed $answers): bool
    {
        if (! is_array($answers)) {
            return false;
        }

        $stored = $registration->securityQuestions->keyBy('question_number');
        if ($stored->count() !== 3) {
            return false;
        }

        $seen = [];
        foreach (array_values($answers) as $answer) {
            if (! is_array($answer)) {
                return false;
            }
            $number = (int) ($answer['question_number'] ?? 0);
            if (isset($seen[$number]) || ! $stored->has($number)) {
                return false;
            }
            $seen[$number] = true;
            $choice = trim((string) ($answer['selected_choice'] ?? ''));
            $custom = trim((string) ($answer['custom_answer'] ?? ''));
            $attempt = strcasecmp($choice, 'Iba pa') === 0 || $choice === '' ? $custom : $choice;
            if (! $this->securityQuestions->matches($stored->get($number), $attempt)) {
                return false;
            }
        }

        return count($seen) === 3;
    }

    private function assertEmailAvailableForClaim(KabataanRegistration $registration, string $email): void
    {
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already taken. Please use another email.'],
            ]);
        }

        $taken = KabataanRegistration::query()
            ->where('id', '!=', $registration->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('deleted_at')
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'email' => ['This email is already taken. Please use another email.'],
            ]);
        }

        $this->drafts->assertEmailAvailable($email, (int) $registration->barangay_id);
    }

    public function requireTurnstile(Request $request): void
    {
        if (! $this->turnstile->isEnabled()) {
            return;
        }

        $token = (string) $request->input('cf-turnstile-response', '');
        if ($token === '' || ! $this->turnstile->verify($token, $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => ['Please complete the security verification and try again.'],
            ]);
        }
    }

    private function recordFailure(Request $request): void
    {
        $token = $this->rememberDevice($request);
        $row = GuestClaimLockout::query()->firstOrCreate(
            ['device_token' => $token],
            ['failed_attempts' => 0]
        );

        if ($this->remainingLockSeconds($row) > 0) {
            return;
        }

        $attempts = (int) $row->failed_attempts + 1;
        $row->forceFill([
            'failed_attempts' => $attempts >= self::MAX_ATTEMPTS ? 0 : $attempts,
            'locked_until' => $attempts >= self::MAX_ATTEMPTS ? now()->addMinutes(self::LOCK_MINUTES) : null,
        ])->save();
    }

    private function clearFailures(Request $request): void
    {
        $token = (string) $request->cookie(self::COOKIE, '');
        if ($token === '') {
            return;
        }

        GuestClaimLockout::query()->where('device_token', $token)->update([
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    private function lockout(Request $request): ?GuestClaimLockout
    {
        $token = (string) $request->cookie(self::COOKIE, '');
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return GuestClaimLockout::query()->where('device_token', $token)->first();
    }

    private function rememberDevice(Request $request): string
    {
        $token = (string) $request->cookie(self::COOKIE, '');
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            Cookie::queue(cookie(
                self::COOKIE,
                $token,
                60 * 24 * 30,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
            $request->cookies->set(self::COOKIE, $token);
        }

        return $token;
    }

    private function remainingLockSeconds(GuestClaimLockout $row): int
    {
        if ($row->locked_until === null || $row->locked_until->isPast()) {
            return 0;
        }

        return max(1, $row->locked_until->getTimestamp() - now()->getTimestamp());
    }

    private function assertNotLocked(Request $request): void
    {
        $status = $this->lockStatus($request);
        if ($status['locked']) {
            throw ValidationException::withMessages([
                'claim' => [$this->lockMessage($status['remaining_seconds'])],
            ]);
        }
    }

    private function lockMessage(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);
        $remain = max(0, $seconds) % 60;

        return 'Masyadong maraming maling subok. Maaari kang sumubok muli pagkalipas ng '.$minutes.':'.str_pad((string) $remain, 2, '0', STR_PAD_LEFT).'.';
    }

    /**
     * @return array{locked: bool, remaining_seconds: int, attempts_left: int}
     */
    private function statusPayload(bool $locked, int $remaining, int $attemptsLeft): array
    {
        return [
            'locked' => $locked,
            'remaining_seconds' => $remaining,
            'attempts_left' => $attemptsLeft,
        ];
    }
}
