<?php

namespace App\Modules\KKProfiling\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Notifications\KabataanProfilingEmailChangeVerify;
use App\Notifications\KabataanProfilingUpdatedEmail;
use App\Notifications\KabataanVerifyEmail;
use App\Rules\FacebookProfileUrl;
use App\Services\BarangayLogoUrlService;
use App\Services\BarangayZoneService;
use App\Services\KabataanNotificationService;
use App\Services\KabataanPhotoService;
use App\Services\KabataanProfilingHistoryService;
use App\Services\KkProfilingScheduleService;
use App\Services\KkRegistrationDraftService;
use App\Services\KkSurveyResponseService;
use App\Services\RegistrationEvaluationService;
use App\Services\TurnstileService;
use App\Support\MailUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class KKProfilingController extends Controller
{
    public function __construct(
        protected KabataanPhotoService $photoService,
        protected BarangayZoneService $barangayZoneService,
        protected TurnstileService $turnstileService,
    ) {}

    /**
     * Display signup page with barangay selector
     */
    public function showSignup(Request $request)
    {
        if ($request->boolean('clear')) {
            $draftService = app(KkRegistrationDraftService::class);
            $draftService->clearSessionDraft();
            $draftService->clearCompletedRegistration();
        }

        $logoService = app(BarangayLogoUrlService::class);
        $barangays = Barangay::orderBy('name')->get(['id', 'name'])->map(function ($barangay) use ($logoService) {
            return [
                'id' => $barangay->id,
                'name' => $barangay->name,
                'logo_url' => $logoService->resolve($barangay->id),
            ];
        });

        return view('kkprofiling::signup', compact('barangays'));
    }

    /**
     * Return the most relevant schedule status per barangay for the signup page.
     * Priority: Ongoing > Upcoming > Rescheduled > Completed > Cancelled
     */
    public function openBarangays()
    {
        $today = now()->toDateString();

        // Include active and upcoming schedules (not yet expired)
        $rows = DB::table('kk_profiling_schedules')
            ->where('date_expiry', '>=', $today)
            ->get(['barangay_id', 'status', 'date_start', 'date_expiry']);

        $priority = ['Ongoing' => 0, 'Upcoming' => 1, 'Rescheduled' => 2, 'Completed' => 3, 'Cancelled' => 4];

        $map = [];
        foreach ($rows as $row) {
            $id = $row->barangay_id;
            $p = $priority[$row->status] ?? 99;
            if (! isset($map[$id]) || $p < ($priority[$map[$id]->status] ?? 99)) {
                $map[$id] = $row;
            }
        }

        $result = array_values(array_map(function ($row) use ($today) {
            $isOpen = $row->status === 'Ongoing'
                && $row->date_start <= $today
                && $row->date_expiry >= $today;

            return [
                'barangay_id' => $row->barangay_id,
                'status' => $row->status,
                'date_start' => $row->date_start,
                'date_expiry' => $row->date_expiry,
                'is_open' => $isOpen,
            ];
        }, $map));

        return response()->json(['schedules' => $result]);
    }

    /**
     * Display the KK Profiling form for a specific barangay
     */
    public function show(string $barangay)
    {
        // Normalize slug — strip poblacion suffix if present
        $slug = strtolower(trim($barangay));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Map display names — must match exact names in the barangays DB table
        $barangayMap = [
            'alipit' => 'Alipit',
            'bagumbayan' => 'Bagumbayan',
            'poblacion-i' => 'Poblacion I',
            'poblacion-ii' => 'Poblacion II',
            'poblacion-iii' => 'Poblacion III',
            'poblacion-iv' => 'Poblacion IV',
            'poblacion-v' => 'Poblacion V',
            'bubukal' => 'Bubukal',
            'calios' => 'Calios',
            'duhat' => 'Duhat',
            'gatid' => 'Gatid',
            'jasaan' => 'Jasaan',
            'labuin' => 'Labuin',
            'malinao' => 'Malinao',
            'oogong' => 'Oogong',
            'pagsawitan' => 'Pagsawitan',
            'palasan' => 'Palasan',
            'patimbao' => 'Patimbao',
            'san-jose' => 'San Jose',
            'san-juan' => 'San Juan',
            'san-pablo-norte' => 'San Pablo Norte',
            'san-pablo-sur' => 'San Pablo Sur',
            'santisima-cruz' => 'Santisima Cruz',
            'santo-angel-central' => 'Santo Angel Central',
            'santo-angel-norte' => 'Santo Angel Norte',
            'santo-angel-sur' => 'Santo Angel Sur',
        ];

        if (! array_key_exists($slug, $barangayMap)) {
            abort(404);
        }

        $displayName = $barangayMap[$slug];

        // ── Schedule gate ──────────────────────────────────────────────────
        // Block access if the barangay has no active KK Profiling schedule.
        $barangayRecord = Barangay::where('name', $displayName)->first();

        if (! $barangayRecord) {
            abort(404);
        }

        $today = now()->toDateString();
        $hasActiveSchedule = DB::table('kk_profiling_schedules')
            ->where('barangay_id', $barangayRecord->id)
            ->where('status', 'Ongoing')
            ->where('date_start', '<=', $today)
            ->where('date_expiry', '>=', $today)
            ->exists();

        $hasAnySchedule = DB::table('kk_profiling_schedules')
            ->where('barangay_id', $barangayRecord->id)
            ->exists();

        if (! $hasActiveSchedule) {
            $message = $hasAnySchedule
                ? 'KK Profiling sign-up for '.$displayName.' is not currently open. Please wait for the next schedule.'
                : 'This barangay ('.$displayName.') has no scheduled KK Profiling yet. Please contact your barangay SK officials for more information.';

            return redirect()->route('kkprofiling.signup')
                ->withErrors(['schedule' => $message]);
        }
        // ──────────────────────────────────────────────────────────────────

        $respondentNumber = '';

        $draftService = app(KkRegistrationDraftService::class);
        $wizard = $draftService->resolveWizard();

        $wizardInitialStep = 1;
        $verificationSent = false;
        $registrationComplete = false;
        $completedEmail = null;
        $wizardDraftEmail = null;
        $registrationAutoApproved = false;

        $completedSession = $draftService->resolveCompletedRegistration((int) $barangayRecord->id);

        if ($completedSession) {
            $registrationComplete = true;
            $completedEmail = $completedSession['email'];

            $registration = KabataanRegistration::query()
                ->where('barangay_id', $barangayRecord->id)
                ->where('email', strtolower(trim($completedSession['email'])))
                ->whereIn('status', ['password_set', 'active'])
                ->latest('id')
                ->first();

            $registrationAutoApproved = $registration
                ? RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status)
                : (bool) ($completedSession['auto_approved'] ?? false);

            if ($registration && $registrationAutoApproved) {
                $draftService->markRegistrationComplete(
                    (string) $completedSession['email'],
                    (int) $barangayRecord->id,
                    $registration,
                );
            }
        }

        if ($wizard && (int) ($wizard['barangay_id'] ?? 0) === (int) $barangayRecord->id) {
            $verificationSent = ! empty($wizard['verification_sent_at']);
            $respondentNumber = $wizard['respondent_number'] ?? $respondentNumber;

            $wizardEmail = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? ''));

            if (! $registrationComplete && $wizardEmail !== '' && $draftService->isEmailRegistrationComplete($wizardEmail, (int) $barangayRecord->id)) {
                $registration = KabataanRegistration::query()
                    ->where('barangay_id', $barangayRecord->id)
                    ->where('email', $wizardEmail)
                    ->whereIn('status', ['password_set', 'active'])
                    ->latest('id')
                    ->first();

                $draftService->markRegistrationComplete($wizardEmail, (int) $barangayRecord->id, $registration);
                $registrationComplete = true;
                $completedEmail = $wizardEmail;
                $registrationAutoApproved = $registration
                    ? RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status)
                    : false;
            }
        }

        if ($registrationComplete) {
            $wizardInitialStep = 3;
            $wizardDraftEmail = $completedEmail;
        } else {
            $wizardDraftEmail = null;
            $verificationSent = false;
        }

        return view('kkprofiling::kkprofiling', [
            'barangay' => $displayName,
            'slug' => $slug,
            'respondentNumber' => $respondentNumber,
            'respondentDisplay' => self::formatRespondentDisplay($respondentNumber),
            'barangayLogoUrl' => self::getBarangayLogoUrl($barangayRecord->id),
            'barangayZones' => $this->barangayZoneService->activeZonesForBarangay((int) $barangayRecord->id),
            'wizardInitialStep' => $wizardInitialStep,
            'verificationSent' => $verificationSent,
            'registrationComplete' => $registrationComplete,
            'completedEmail' => $completedEmail,
            'registrationAutoApproved' => $registrationAutoApproved,
            'wizardDraftEmail' => $wizardDraftEmail,
            'turnstileEnabled' => app(TurnstileService::class)->isEnabled(),
            'turnstileSiteKey' => app(TurnstileService::class)->getSiteKey(),
        ]);
    }

    public static function getBarangayLogoUrl(?int $barangayId): ?string
    {
        return app(BarangayLogoUrlService::class)->resolve($barangayId);
    }

    /**
     * Format respondent number for read-only display (e.g. 01).
     */
    public static function formatRespondentDisplay(?string $respondentNumber): string
    {
        if (! $respondentNumber) {
            return '01';
        }

        if (preg_match('/(\d+)$/', $respondentNumber, $matches)) {
            $n = ((int) $matches[1]) % 100;

            return str_pad($n ?: 1, 2, '0', STR_PAD_LEFT);
        }

        return str_pad((abs(crc32($respondentNumber)) % 99) + 1, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Yearly KK Profiling update for authenticated youth.
     */
    public function updateForUser(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('sign-in');
        }

        $registration = KabataanRegistration::where('user_id', $user->id)->latest()->first();
        if (! $registration) {
            return redirect()->route('dashboard')
                ->withErrors(['kk_profiling' => 'No KK Profiling record found for your account.']);
        }

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z.\-]{3,50}$/'],
            'first_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^(?!\s)[A-Za-z.\-\s]+$/'],
            'middle_name' => ['nullable', 'string', 'max:50', 'regex:/^$|^[A-Za-z.\-]{3,50}$/'],
            'suffix' => ['required', 'string', 'in:None,Jr.,Sr.,I,II,III,IV,V,Others'],
            'custom_suffix' => ['nullable', 'required_if:suffix,Others', 'string', 'max:5', 'regex:/^(?!\s+$)[A-Za-z.\s]+$/'],
            'purok_zone' => $this->barangayZoneService->purokZoneRules((int) $registration->barangay_id),
            'sex' => 'required|in:Male,Female',
            'age' => 'required|integer|min:15|max:30',
            'birthday' => 'required|date|before_or_equal:today',
            'email' => ['required', 'email', 'max:254', 'regex:/^[A-Za-z0-9._%+-]{6,30}@gmail\.com$/i'],
            'contact_number' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'civil_status' => 'required|string',
            'youth_classification' => 'required|string',
            'youth_age_group' => 'required|string',
            'work_status' => 'required|string',
            'education' => 'required|string',
            'sk_voter' => 'required|string',
            'national_voter' => 'required|string',
            'sk_voted' => 'required|string',
            'kk_assembly' => 'required|string|in:Yes,No',
            'kk_times' => 'required_if:kk_assembly,Yes|nullable|string',
            'kk_reason' => 'required_if:kk_assembly,No|nullable|string',
            'facebook_profile_url' => [
                'nullable',
                Rule::requiredIf(fn () => in_array((string) $request->input('group_chat'), ['Yes', 'No'], true)),
                'string',
                'min:3',
                'max:50',
                new FacebookProfileUrl,
            ],
            'group_chat' => [
                'nullable',
                Rule::requiredIf(fn () => trim((string) $request->input('facebook_profile_url', '')) !== ''),
                'string',
                Rule::in(['Yes', 'No']),
            ],
            'signature' => 'required|string',
        ]);

        $this->normalizeProfilingSuffix($validated);

        if (($validated['suffix'] ?? null) === 'Others') {
            $customSuffix = trim((string) ($validated['custom_suffix'] ?? ''));
            $compact = strtoupper(str_replace(' ', '', $customSuffix));
            $validRoman = in_array($compact, ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'], true);
            $validText = (bool) preg_match('/^[A-Za-z.]+$/', str_replace(' ', '', $customSuffix));

            if (! $validRoman && ! $validText) {
                return $this->updateErrorResponse($request, [
                    'custom_suffix' => 'Only text and valid Roman numeral suffixes are allowed.',
                ]);
            }

            if (strlen(str_replace(' ', '', $customSuffix)) > 5) {
                return $this->updateErrorResponse($request, [
                    'custom_suffix' => 'Suffix must not exceed 5 characters.',
                ]);
            }
        }

        try {
            $derivedAge = Carbon::parse($validated['birthday'])->age;
            if ($derivedAge < 15 || $derivedAge > 30 || (int) $validated['age'] !== (int) $derivedAge) {
                return $this->updateErrorResponse($request, [
                    'birthday' => 'Birthday and age must match and be within 15 to 30 years old.',
                ]);
            }
        } catch (\Throwable $e) {
            return $this->updateErrorResponse($request, [
                'birthday' => 'Invalid birthday value.',
            ]);
        }

        $scheduleService = app(KkProfilingScheduleService::class);
        $schedule = $scheduleService->activeUpdateSchedule((int) $registration->barangay_id);
        $profilingYear = $scheduleService->scheduleProfilingYear($schedule);

        if (! $scheduleService->requiresProfilingUpdate($registration)) {
            return redirect()->route('dashboard')
                ->withErrors(['kk_profiling' => 'KK Profiling update is not currently required.']);
        }

        $existingForm = $registration->form_data ?? [];
        if (! empty($existingForm['respondent_number'])) {
            $validated['respondent_number'] = $existingForm['respondent_number'];
        } else {
            unset($validated['respondent_number']);
        }

        $originalEmail = strtolower(trim((string) $registration->email));
        $validated['email'] = $originalEmail;
        $validated['profile_updated_year'] = $profilingYear;
        $validated['profile_updated_at'] = now()->toIso8601String();
        $validated = $this->mergeProfilingUpdatePayload($request, $validated, $existingForm);

        $this->applyProfilingUpdate(
            $registration,
            $user,
            $validated,
            $profilingYear,
            $schedule ? (int) $schedule->id : null,
            $originalEmail,
        );

        $barangayName = $registration->barangay?->name ?? 'your barangay';
        session()->flash('kabataan_toast', [
            'title' => 'Congratulations!',
            'message' => "You've successfully updated your KK Profiling for {$profilingYear}.",
        ]);
        session()->put('kk_profiling_update_required', false);

        return $this->updateSuccessResponse($request, [
            'message' => 'Your KK Profiling information for '.$profilingYear.' has been updated successfully. A confirmation email has been sent to '.$originalEmail.'.',
            'notification' => [
                'title' => 'KK Profiling Updated',
                'message' => "Congratulations! You've successfully updated your KK Profiling for {$profilingYear} in {$barangayName}.",
            ],
        ]);
    }

    public function verifyUpdateEmail(Request $request, int $id, string $hash)
    {
        if (! URL::hasValidSignature($request)) {
            return redirect()->route('dashboard')->withErrors([
                'kk_profiling' => 'The verification link is invalid or expired.',
            ]);
        }

        $registration = KabataanRegistration::find($id);
        $pending = $registration
            ? Cache::get($this->pendingUpdateCacheKey($registration->id))
            : null;

        if (! $registration || ! is_array($pending)) {
            return redirect()->route('dashboard')->withErrors([
                'kk_profiling' => 'No pending KK Profiling update was found. Please submit the update again.',
            ]);
        }

        $newEmail = strtolower(trim((string) ($pending['new_email'] ?? '')));
        if ($newEmail === '' || ! hash_equals($hash, sha1($newEmail))) {
            return redirect()->route('dashboard')->withErrors([
                'kk_profiling' => 'The verification link is invalid.',
            ]);
        }

        $user = User::find($registration->user_id);
        if (! $user) {
            return redirect()->route('dashboard')->withErrors([
                'kk_profiling' => 'Unable to complete your KK Profiling update.',
            ]);
        }

        $validated = is_array($pending['validated'] ?? null) ? $pending['validated'] : [];
        $validated['email'] = $newEmail;

        $this->applyProfilingUpdate(
            $registration,
            $user,
            $validated,
            (int) ($pending['profiling_year'] ?? now()->year),
            isset($pending['schedule_id']) ? (int) $pending['schedule_id'] : null,
            $newEmail,
        );

        Cache::forget($this->pendingUpdateCacheKey($registration->id));

        return redirect()->route('dashboard')
            ->with('success', 'Your email has been verified and your KK Profiling update is complete.');
    }

    public function resendUpdateEmailVerification(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $registration = KabataanRegistration::where('user_id', $user->id)->latest()->first();
        if (! $registration) {
            return response()->json(['success' => false, 'message' => 'No KK Profiling record found.'], 404);
        }

        $pending = Cache::get($this->pendingUpdateCacheKey($registration->id));
        if (! is_array($pending) || empty($pending['new_email'])) {
            return response()->json([
                'success' => false,
                'message' => 'No pending email change found. Please submit your KK Profiling update again.',
            ], 422);
        }

        $newEmail = strtolower(trim((string) $pending['new_email']));
        $profilingYear = (int) ($pending['profiling_year'] ?? now()->year);

        $retryAfter = $this->resendVerificationCooldownSeconds($registration->id);
        if ($retryAfter > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait '.$retryAfter.' seconds before requesting another verification email.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        $verificationUrl = MailUrl::temporarySignedRoute(
            'kkprofiling.verify-update',
            now()->addHours(24),
            [
                'id' => $registration->id,
                'hash' => sha1($newEmail),
            ],
        );

        Notification::route('mail', $newEmail)
            ->notify(new KabataanProfilingEmailChangeVerify($verificationUrl, $profilingYear));

        $this->markResendVerificationCooldown($registration->id);

        return response()->json([
            'success' => true,
            'message' => 'Verification email has been resent. Please check your inbox.',
            'email' => $newEmail,
            'retry_after' => 60,
        ]);
    }

    private function pendingUpdateCacheKey(int $registrationId): string
    {
        return 'kk_profiling_update_pending:'.$registrationId;
    }

    private function resendVerificationCooldownKey(int $registrationId): string
    {
        return 'kk_profiling_resend_cooldown:'.$registrationId;
    }

    private function resendVerificationCooldownSeconds(int $registrationId): int
    {
        $expiresAt = Cache::get($this->resendVerificationCooldownKey($registrationId));
        if (! is_int($expiresAt)) {
            return 0;
        }

        return max(0, $expiresAt - time());
    }

    private function markResendVerificationCooldown(int $registrationId, int $seconds = 60): void
    {
        Cache::put(
            $this->resendVerificationCooldownKey($registrationId),
            time() + $seconds,
            now()->addSeconds($seconds),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function normalizeProfilingSuffix(array &$validated): void
    {
        $suffix = trim((string) ($validated['suffix'] ?? ''));
        if ($suffix === '' || strcasecmp($suffix, 'none') === 0) {
            $validated['suffix'] = 'None';
            $validated['custom_suffix'] = null;

            return;
        }

        if ($suffix === 'Others') {
            $custom = trim((string) ($validated['custom_suffix'] ?? ''));
            if ($custom !== '') {
                $validated['suffix'] = $custom;
            }
            $validated['custom_suffix'] = $custom !== '' ? $custom : null;

            return;
        }

        $validated['custom_suffix'] = null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $existingForm
     * @return array<string, mixed>
     */
    private function mergeProfilingUpdatePayload(Request $request, array $validated, array $existingForm): array
    {
        $validated['civil_status'] = $request->input('civil_status', []);
        $validated['youth_classification'] = $request->input('youth_classification', []);
        $validated['youth_age_group'] = $request->input('youth_age_group', []);
        $validated['work_status'] = $request->input('work_status', []);
        $validated['education'] = $request->input('education', []);
        $validated['sk_voter'] = $request->input('sk_voter');
        $validated['national_voter'] = $request->input('national_voter');
        $validated['sk_voted'] = $request->input('sk_voted');
        $validated['kk_assembly'] = $request->input('kk_assembly');
        $validated['kk_times'] = $request->input('kk_assembly') === 'Yes'
            ? ($request->input('kk_times') ?: $request->input('kk_timesChk'))
            : null;
        $validated['kk_reason'] = $request->input('kk_assembly') === 'No'
            ? ($request->input('kk_reason') ?: $request->input('kk_reasonChk'))
            : null;
        $validated['facebook_profile_url'] = trim((string) $request->input('facebook_profile_url', '')) ?: null;
        $validated['group_chat'] = $request->input('group_chat');
        $validated['signature_name'] = $request->input('signature_name');

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyProfilingUpdate(
        KabataanRegistration $registration,
        User $user,
        array $validated,
        int $profilingYear,
        ?int $scheduleId,
        string $email,
    ): void {
        $this->normalizeProfilingSuffix($validated);

        $existingForm = is_array($registration->form_data) ? $registration->form_data : [];
        $email = strtolower(trim($email));
        $validated['email'] = $email;

        DB::transaction(function () use ($registration, $user, $validated, $existingForm, $email, $profilingYear, $scheduleId) {
            $registration->update([
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'suffix' => $validated['suffix'] ?? null,
                'email' => $email,
                'contact_number' => $validated['contact_number'] ?? null,
                'form_data' => array_merge($existingForm, $validated),
                'submitted_at' => now(),
            ]);

            if (strtolower((string) $user->email) !== $email) {
                $user->update([
                    'email' => $email,
                    'email_verified_at' => now(),
                ]);
            }

            $registration->refresh();
            app(KabataanProfilingHistoryService::class)->saveSnapshot(
                $registration,
                $profilingYear,
                $scheduleId,
            );
            app(KkProfilingScheduleService::class)->forgetCompletionCache(
                (int) $registration->id,
                $profilingYear,
            );

            app(KkSurveyResponseService::class)->syncFromRegistration($registration, 'approved');
        });

        $registration->refresh();
        $barangayName = $registration->barangay?->name ?? 'your barangay';

        try {
            app(KabataanNotificationService::class)->notifyKkProfilingUpdated(
                $user,
                $profilingYear,
                $barangayName,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            Notification::route('mail', $email)
                ->notify(new KabataanProfilingUpdatedEmail(
                    $registration->full_name,
                    $profilingYear,
                    $barangayName,
                ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateSuccessResponse(Request $request, array $payload)
    {
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(array_merge(['success' => true, 'redirect' => route('dashboard')], $payload));
        }

        return redirect()->route('dashboard')->with('success', $payload['message'] ?? 'Update complete.');
    }

    /**
     * @param  array<string, string|array<int, string>>  $errors
     */
    private function updateErrorResponse(Request $request, array $errors)
    {
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first(),
                'errors' => collect($errors)->map(fn ($msg) => is_array($msg) ? $msg : [$msg])->all(),
            ], 422);
        }

        return back()->withInput()->withErrors($errors);
    }

    /**
     * Handle KK Profiling form submission
     */
    public function submit(Request $request, string $barangay)
    {
        \Log::info('Form submission received', [
            'barangay' => $barangay,
            'data' => $request->all(),
        ]);

        $slug = $this->normalizeSlug($barangay);
        $barangayName = $this->getBarangayName($slug);

        if (! $barangayName) {
            abort(404);
        }

        $barangayRecord = Barangay::where('name', $barangayName)->first();

        if (! $barangayRecord) {
            abort(404);
        }

        $today = now()->toDateString();
        $hasActiveSchedule = DB::table('kk_profiling_schedules')
            ->where('barangay_id', $barangayRecord->id)
            ->where('status', 'Ongoing')
            ->where('date_start', '<=', $today)
            ->where('date_expiry', '>=', $today)
            ->exists();

        $hasAnySchedule = DB::table('kk_profiling_schedules')
            ->where('barangay_id', $barangayRecord->id)
            ->exists();

        if (! $hasActiveSchedule) {
            $message = $hasAnySchedule
                ? 'KK Profiling sign-up for '.$barangayName.' is not currently open. Please wait for the next schedule.'
                : 'This barangay ('.$barangayName.') has no scheduled KK Profiling yet. Please contact your barangay SK officials for more information.';

            return $this->submitErrorResponse($request, ['schedule' => $message]);
        }

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z.\-]{3,50}$/'],
            'first_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^(?!\s)[A-Za-z.\-\s]+$/'],
            'middle_name' => ['nullable', 'string', 'max:50', 'regex:/^$|^[A-Za-z.\-]{3,50}$/'],
            'suffix' => ['required', 'string', 'in:None,Jr.,Sr.,I,II,III,IV,V,Others'],
            'custom_suffix' => ['nullable', 'required_if:suffix,Others', 'string', 'max:5', 'regex:/^(?!\s+$)[A-Za-z.\s]+$/'],
            'purok_zone' => $this->barangayZoneService->purokZoneRules((int) $barangayRecord->id),
            'sex' => 'required|in:Male,Female',
            'age' => 'required|integer|min:15|max:30',
            'birthday' => 'required|date|before_or_equal:today',
            'email' => ['required', 'email', 'max:254', 'regex:/^[A-Za-z0-9._%+-]{6,30}@gmail\.com$/i'],
            'contact_number' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'civil_status' => 'required|string',
            'youth_classification' => 'required|string',
            'youth_age_group' => 'required|string',
            'work_status' => 'required|string',
            'education' => 'required|string',
            'sk_voter' => 'required|string',
            'national_voter' => 'required|string',
            'sk_voted' => 'required|string',
            'kk_assembly' => 'required|string|in:Yes,No',
            'kk_times' => 'required_if:kk_assembly,Yes|nullable|string',
            'kk_reason' => 'required_if:kk_assembly,No|nullable|string',
            'facebook_profile_url' => [
                'nullable',
                Rule::requiredIf(fn () => in_array((string) $request->input('group_chat'), ['Yes', 'No'], true)),
                'string',
                'min:3',
                'max:50',
                new FacebookProfileUrl,
            ],
            'group_chat' => [
                'nullable',
                Rule::requiredIf(fn () => trim((string) $request->input('facebook_profile_url', '')) !== ''),
                'string',
                Rule::in(['Yes', 'No']),
            ],
            'signature' => 'required|string',
        ]);

        if (($validated['suffix'] ?? null) === 'Others') {
            $customSuffix = trim((string) ($validated['custom_suffix'] ?? ''));
            $compact = strtoupper(str_replace(' ', '', $customSuffix));
            $validRoman = in_array($compact, ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'], true);
            $validText = (bool) preg_match('/^[A-Za-z.]+$/', str_replace(' ', '', $customSuffix));

            if (! $validRoman && ! $validText) {
                return $this->submitErrorResponse($request, [
                    'custom_suffix' => 'Only text and valid Roman numeral suffixes are allowed.',
                ]);
            }

            if ($validRoman && (strlen($compact) < 1 || strlen($compact) > 5)) {
                return $this->submitErrorResponse($request, [
                    'custom_suffix' => 'Suffix must not exceed 5 characters.',
                ]);
            }

            if (! $validRoman && strlen(str_replace(' ', '', $customSuffix)) > 5) {
                return $this->submitErrorResponse($request, [
                    'custom_suffix' => 'Suffix must not exceed 5 characters.',
                ]);
            }
        }

        // Server-side age consistency from birthday (15-30 only)
        try {
            $derivedAge = Carbon::parse($validated['birthday'])->age;
            if ($derivedAge < 15 || $derivedAge > 30 || (int) $validated['age'] !== (int) $derivedAge) {
                return $this->submitErrorResponse($request, [
                    'birthday' => 'Birthday and age must match and be within 15 to 30 years old.',
                ]);
            }
        } catch (\Throwable $e) {
            return $this->submitErrorResponse($request, [
                'birthday' => 'Invalid birthday value.',
            ]);
        }

        unset($validated['respondent_number']);
        $scheduleService = app(KkProfilingScheduleService::class);
        $activeSchedule = $scheduleService->activeUpdateSchedule((int) $barangayRecord->id);
        if ($activeSchedule !== null) {
            $today = now($scheduleService->timezone())->toDateString();
            if ($today >= (string) $activeSchedule->date_start && $today <= (string) $activeSchedule->date_expiry) {
                $validated['profile_updated_year'] = $scheduleService->scheduleProfilingYear($activeSchedule);
                $validated['profile_updated_at'] = now()->toIso8601String();
            }
        }
        $validated['civil_status'] = $request->input('civil_status', []);
        $validated['youth_classification'] = $request->input('youth_classification', []);
        $validated['youth_age_group'] = $request->input('youth_age_group', []);
        $validated['work_status'] = $request->input('work_status', []);
        $validated['education'] = $request->input('education', []);
        $validated['sk_voter'] = $request->input('sk_voter');
        $validated['national_voter'] = $request->input('national_voter');
        $validated['sk_voted'] = $request->input('sk_voted');
        $validated['kk_assembly'] = $request->input('kk_assembly');
        $validated['kk_times'] = $request->input('kk_assembly') === 'Yes'
            ? ($request->input('kk_times') ?: $request->input('kk_timesChk'))
            : null;
        $validated['kk_reason'] = $request->input('kk_assembly') === 'No'
            ? ($request->input('kk_reason') ?: $request->input('kk_reasonChk'))
            : null;
        $validated['facebook_profile_url'] = trim((string) $request->input('facebook_profile_url', '')) ?: null;
        $validated['group_chat'] = $request->input('group_chat');

        \Log::info('Validation passed');

        $email = strtolower(trim($validated['email']));
        $existingUser = User::where('email', $email)
            ->whereIn('status', ['ACTIVE', 'PENDING_APPROVAL', 'INACTIVE'])
            ->exists();

        $verifiedRegistration = KabataanRegistration::where('email', $email)
            ->where('barangay_id', $barangayRecord->id)
            ->where('status', 'email_verified')
            ->exists();

        if ($existingUser || $verifiedRegistration) {
            return $this->submitErrorResponse($request, [
                'email' => 'This email already exists. Please use a different email address.',
            ]);
        }

        $existingRegistration = KabataanRegistration::where('email', $validated['email'])
            ->where('barangay_id', $barangayRecord->id)
            ->first();

        $registration = KabataanRegistration::updateOrCreate(
            [
                'email' => $validated['email'],
                'barangay_id' => $barangayRecord->id,
            ],
            [
                'tenant_id' => $barangayRecord->tenant_id,
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'suffix' => $validated['suffix'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'profile_photo_path' => null,
                'form_data' => $validated,
                'status' => 'pending_verification',
                'evaluation_status' => null,
                'evaluation_notes' => null,
                'review_notes' => null,
                'submitted_at' => now(),
            ]
        );

        try {
            (new KkSurveyResponseService)->syncFromRegistration($registration->fresh(), 'pending');
        } catch (\Throwable $e) {
            report($e);
        }

        // Send verification email
        try {
            $this->sendVerificationEmail($registration);
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Check if request is AJAX (from JavaScript fetch)
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please check your email for verification.',
                'redirect' => route('kkprofiling.check-email'),
                'email' => $registration->email,
                'barangay' => $barangay,
            ]);
        }

        // Fallback to normal redirect for non-AJAX requests
        return redirect()
            ->route('kkprofiling.check-email')
            ->with('email', $registration->email)
            ->with('barangay', $barangay);
    }

    /**
     * Check if an email is already registered (users or in-progress KK registration).
     */
    public function checkEmailExists(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'regex:/^[^\s]+@gmail\.com$/i'],
            'current_email' => ['nullable', 'email'],
        ]);

        $email = strtolower(trim($request->email));
        $currentEmail = strtolower(trim((string) $request->input('current_email', '')));

        if ($currentEmail !== '' && $email === $currentEmail) {
            return response()->json([
                'exists' => false,
                'message' => null,
            ]);
        }

        if (Auth::check()) {
            $ownRegistration = KabataanRegistration::query()
                ->where('user_id', Auth::id())
                ->latest('id')
                ->first();

            if ($ownRegistration && $email === strtolower(trim((string) $ownRegistration->email))) {
                return response()->json([
                    'exists' => false,
                    'message' => null,
                ]);
            }
        }

        $existingUser = User::where('email', $email)
            ->whereIn('status', ['ACTIVE', 'PENDING_APPROVAL', 'INACTIVE'])
            ->exists();

        $verifiedRegistration = KabataanRegistration::where('email', $email)
            ->where('status', 'email_verified')
            ->exists();

        $exists = $existingUser || $verifiedRegistration;

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'This email already exists. Please use a different email address.' : null,
        ]);
    }

    /**
     * Resend KK Profiling email verification link.
     */
    public function resendVerification(Request $request)
    {
        if ($fail = $this->turnstileService->requestFailed($request)) {
            return response()->json([
                'success' => false,
                'message' => $fail,
            ], 422);
        }

        $request->validate([
            'email' => ['required', 'email'],
            'barangay' => ['nullable', 'string'],
        ]);

        $registration = KabataanRegistration::where('email', $request->email);

        if ($request->filled('barangay')) {
            $slug = $this->normalizeSlug($request->barangay);
            $barangayName = $this->getBarangayName($slug);
            if ($barangayName) {
                $barangayRecord = Barangay::where('name', $barangayName)->first();
                if ($barangayRecord) {
                    $registration->where('barangay_id', $barangayRecord->id);
                }
            }
        }

        $registration = $registration->latest()->first();

        if (! $registration) {
            return response()->json([
                'success' => false,
                'message' => 'No registration found for this email address.',
            ], 404);
        }

        if ($registration->status !== 'pending_verification') {
            return response()->json([
                'success' => false,
                'message' => 'This email has already been verified or registration is complete.',
            ], 422);
        }

        try {
            $this->sendVerificationEmail($registration);

            return response()->json([
                'success' => true,
                'message' => 'Verification email has been resent. Please check your inbox.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to resend verification email', [
                'email' => $registration->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again later.',
            ], 500);
        }
    }

    /**
     * Show check email page after registration
     */
    public function showCheckEmail(Request $request)
    {
        // Try to get email from URL parameter first, then from session
        $email = $request->query('email') ?? session('email');
        $barangay = $request->query('barangay') ?? session('barangay');

        if (! $email) {
            return redirect()->route('kkprofiling.signup');
        }

        return view('kkprofiling::check_email', [
            'email' => $email,
            'barangay' => $barangay,
        ]);
    }

    private function normalizeSlug(string $barangay): string
    {
        $slug = strtolower(trim($barangay));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    private function getBarangayName(string $slug): ?string
    {
        $barangayMap = [
            'alipit' => 'Alipit',
            'bagumbayan' => 'Bagumbayan',
            'poblacion-i' => 'Poblacion I',
            'poblacion-ii' => 'Poblacion II',
            'poblacion-iii' => 'Poblacion III',
            'poblacion-iv' => 'Poblacion IV',
            'poblacion-v' => 'Poblacion V',
            'bubukal' => 'Bubukal',
            'calios' => 'Calios',
            'duhat' => 'Duhat',
            'gatid' => 'Gatid',
            'jasaan' => 'Jasaan',
            'labuin' => 'Labuin',
            'malinao' => 'Malinao',
            'oogong' => 'Oogong',
            'pagsawitan' => 'Pagsawitan',
            'palasan' => 'Palasan',
            'patimbao' => 'Patimbao',
            'san-jose' => 'San Jose',
            'san-juan' => 'San Juan',
            'san-pablo-norte' => 'San Pablo Norte',
            'san-pablo-sur' => 'San Pablo Sur',
            'santisima-cruz' => 'Santisima Cruz',
            'santo-angel-central' => 'Santo Angel Central',
            'santo-angel-norte' => 'Santo Angel Norte',
            'santo-angel-sur' => 'Santo Angel Sur',
        ];

        return $barangayMap[$slug] ?? null;
    }

    /**
     * Verify email from signed URL
     */
    public function verifyEmail(Request $request, int $id, string $hash)
    {
        if (! URL::hasValidSignature($request)) {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'verification' => 'The verification link is invalid or expired.',
            ]);
        }

        $registration = KabataanRegistration::find($id);

        if (! $registration || ! hash_equals($hash, sha1($registration->email))) {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'verification' => 'The verification link is invalid.',
            ]);
        }

        if ($registration->status === 'pending_verification') {
            $registration->markEmailVerified();
        }

        // Store registration ID in session for password setup
        session(['kabataan_registration_id' => $registration->id]);

        return redirect()->route('kkprofiling.set-password', [
            'barangay' => $this->getBarangaySlug($registration->barangay->name),
        ])->with('success', 'Email verified! Please set your password to complete registration.');
    }

    private function submitErrorResponse(Request $request, array $errors)
    {
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first(),
                'errors' => collect($errors)->map(fn ($msg) => is_array($msg) ? $msg : [$msg])->all(),
            ], 422);
        }

        return back()->withInput()->withErrors($errors);
    }

    private function sendVerificationEmail(KabataanRegistration $registration): void
    {
        $verificationUrl = MailUrl::temporarySignedRoute(
            'kkprofiling.verify',
            now()->addHours(24),
            [
                'id' => $registration->id,
                'hash' => sha1($registration->email),
            ]
        );

        \Log::info('Sending verification email', [
            'email' => $registration->email,
            'url' => $verificationUrl,
        ]);

        Notification::route('mail', $registration->email)
            ->notify(new KabataanVerifyEmail($verificationUrl));
    }

    private function getBarangaySlug(string $name): string
    {
        $slugMap = [
            'Alipit' => 'alipit',
            'Bagumbayan' => 'bagumbayan',
            'Poblacion I' => 'poblacion-i',
            'Poblacion II' => 'poblacion-ii',
            'Poblacion III' => 'poblacion-iii',
            'Poblacion IV' => 'poblacion-iv',
            'Poblacion V' => 'poblacion-v',
            'Bubukal' => 'bubukal',
            'Calios' => 'calios',
            'Duhat' => 'duhat',
            'Gatid' => 'gatid',
            'Jasaan' => 'jasaan',
            'Labuin' => 'labuin',
            'Malinao' => 'malinao',
            'Oogong' => 'oogong',
            'Pagsawitan' => 'pagsawitan',
            'Palasan' => 'palasan',
            'Patimbao' => 'patimbao',
            'San Jose' => 'san-jose',
            'San Juan' => 'san-juan',
            'San Pablo Norte' => 'san-pablo-norte',
            'San Pablo Sur' => 'san-pablo-sur',
            'Santisima Cruz' => 'santisima-cruz',
            'Santo Angel Central' => 'santo-angel-central',
            'Santo Angel Norte' => 'santo-angel-norte',
            'Santo Angel Sur' => 'santo-angel-sur',
        ];

        return $slugMap[$name] ?? strtolower(str_replace(' ', '-', $name));
    }

    /**
     * Show the Set Password page (after email verification)
     */
    public function showSetPassword(string $barangay)
    {
        $registrationId = session('kabataan_registration_id');

        if (! $registrationId) {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'password' => 'Please verify your email first.',
            ]);
        }

        $registration = KabataanRegistration::find($registrationId);

        if (! $registration || ! in_array($registration->status, ['email_verified', 'password_set', 'active'], true)) {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'password' => 'Invalid registration session.',
            ]);
        }

        if (in_array($registration->status, ['password_set', 'active'], true)) {
            $draftService = app(KkRegistrationDraftService::class);
            $draftService->markRegistrationComplete(
                strtolower(trim($registration->email)),
                (int) $registration->barangay_id,
                $registration,
            );

            return view('kkprofiling::set_password', [
                'barangay' => $registration->barangay->name,
                'slug' => $barangay,
                'email' => $registration->email,
                'registrationAlreadyComplete' => true,
                'registrationAutoApproved' => RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status),
                'barangayLogoUrl' => self::getBarangayLogoUrl($registration->barangay_id),
            ]);
        }

        return view('kkprofiling::set_password', [
            'barangay' => $registration->barangay->name,
            'slug' => $barangay,
            'email' => $registration->email,
            'registration' => $registration,
            'barangayLogoUrl' => self::getBarangayLogoUrl($registration->barangay_id),
        ]);
    }

    /**
     * Handle password creation after email verification
     */
    public function storePassword(Request $request, string $barangay)
    {
        set_time_limit((int) config('kkprofiling.finalize_time_limit', 180));

        $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.regex' => 'Password must include uppercase, lowercase, number, and special character.',
        ]);

        $registrationId = session('kabataan_registration_id');

        if (! $registrationId) {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'password' => 'Session expired. Please verify your email again.',
            ]);
        }

        $registration = KabataanRegistration::find($registrationId);

        if (! $registration || $registration->status !== 'email_verified') {
            return redirect()->route('kkprofiling.signup')->withErrors([
                'password' => 'Invalid registration session.',
            ]);
        }

        // Create or reactivate user account
        $user = DB::transaction(function () use ($registration, $request) {
            $existing = User::where('email', $registration->email)->first();

            if ($existing) {
                // Resubmission — update existing user
                $existing->update([
                    'name' => $registration->full_name,
                    'password' => bcrypt($request->password),
                    'email_verified_at' => now(),
                    'status' => 'PENDING_APPROVAL',
                    'tenant_id' => $registration->tenant_id,
                    'barangay_id' => $registration->barangay_id,
                    'profile_image_url' => app(KabataanPhotoService::class)->publicUrl($registration->profile_photo_path),
                    'profile_image_uploaded_at' => $registration->facial_verification_completed_at ?? now(),
                ]);
                $user = $existing;
            } else {
                $user = User::create([
                    'name' => $registration->full_name,
                    'email' => $registration->email,
                    'password' => bcrypt($request->password),
                    'email_verified_at' => now(),
                    'tenant_id' => $registration->tenant_id,
                    'barangay_id' => $registration->barangay_id,
                    'role' => 'kabataan',
                    'status' => 'PENDING_APPROVAL',
                    'profile_image_url' => app(KabataanPhotoService::class)->publicUrl($registration->profile_photo_path),
                    'profile_image_uploaded_at' => $registration->facial_verification_completed_at ?? now(),
                ]);
            }

            $registration->markPasswordSet();
            $registration->linkUser($user->id);

            return $user;
        });

        $evaluator = new RegistrationEvaluationService;
        $evaluator->evaluate($registration->fresh());

        try {
            (new KkSurveyResponseService)->syncFromRegistration(
                $registration->fresh(),
                'pending'
            );
        } catch (\Throwable $e) {
            report($e);
        }

        session()->forget('kabataan_registration_id');

        $message = 'Registration completed! Please wait for verification/approval by SK Officials before logging in.';

        // Check if request is AJAX (from JavaScript fetch)
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'auto_approved' => false,
                'message' => $message,
            ]);
        }

        return redirect()->route('sign-in')->with('success', $message);
    }
}
