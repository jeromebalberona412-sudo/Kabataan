<?php

namespace App\Modules\KKProfiling\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Notifications\KabataanProfilingUpdatedEmail;
use App\Notifications\KabataanVerifyEmail;
use App\Rules\ParticipantSignatureImage;
use App\Rules\PhilippineMobileNumber;
use App\Rules\ValidEmailAddress;
use App\Services\BarangayLogoUrlService;
use App\Services\BarangayZoneService;
use App\Services\InvalidEmailService;
use App\Services\KabataanNotificationService;
use App\Services\KabataanPhotoService;
use App\Services\KabataanProfilingHistoryService;
use App\Services\KkProfilingScheduleService;
use App\Services\KkRegistrationDraftService;
use App\Services\KkSurveyResponseService;
use App\Services\PhoneNumberService;
use App\Services\RegistrationEvaluationService;
use App\Services\RespondentNumberService;
use App\Services\TurnstileAttemptGuard;
use App\Services\TurnstileService;
use App\Support\MailUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class KKProfilingController extends Controller
{
    public function __construct(
        protected KabataanPhotoService $photoService,
        protected BarangayZoneService $barangayZoneService,
        protected TurnstileService $turnstileService,
        protected TurnstileAttemptGuard $turnstileGuard,
        protected InvalidEmailService $invalidEmails,
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

        $hasActiveUnfinishedDraft = is_array($wizard)
            && (int) ($wizard['barangay_id'] ?? 0) === (int) $barangayRecord->id
            && ! empty($wizard['step1_data']);

        // An unfinished draft always wins over a leftover success session from a prior submit.
        if ($hasActiveUnfinishedDraft) {
            $draftService->clearCompletedRegistration();
        }

        $completedSession = $hasActiveUnfinishedDraft
            ? null
            : $draftService->resolveCompletedRegistration((int) $barangayRecord->id);

        if ($completedSession) {
            $registration = KabataanRegistration::query()
                ->where('barangay_id', $barangayRecord->id)
                ->where('email', strtolower(trim((string) ($completedSession['email'] ?? ''))))
                ->whereIn('status', ['password_set', 'active'])
                ->latest('id')
                ->first();

            // Success UI only after password was set and the row exists in the database.
            if ($registration) {
                $registrationComplete = true;
                $completedEmail = $completedSession['email'];
                $registrationAutoApproved = RegistrationEvaluationService::isAutoApprovedStatus(
                    $registration->evaluation_status
                );
                $draftService->markRegistrationComplete(
                    (string) $completedSession['email'],
                    (int) $barangayRecord->id,
                    $registration,
                );
            } else {
                $draftService->clearCompletedRegistration();
            }
        }

        if ($wizard && (int) ($wizard['barangay_id'] ?? 0) === (int) $barangayRecord->id) {
            $verificationSent = ! empty($wizard['verification_sent_at']);
            $respondentNumber = $wizard['respondent_number'] ?? $respondentNumber;
            $wizardDraftEmail = strtolower(trim($wizard['email'] ?? $wizard['step1_data']['email'] ?? '')) ?: null;
            $wizardInitialStep = max(1, min(3, (int) ($wizard['current_step'] ?? 1)));
        }

        if ($registrationComplete) {
            $wizardInitialStep = 3;
            $wizardDraftEmail = $completedEmail;
            $verificationSent = true;
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
            'turnstileRequired' => app(TurnstileAttemptGuard::class)->isRequired(
                TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY,
                request()
            ),
        ]);
    }

    public static function getBarangayLogoUrl(?int $barangayId): ?string
    {
        return app(BarangayLogoUrlService::class)->resolve($barangayId);
    }

    /**
     * Format respondent number for read-only display (e.g. 1, 2, 3 or Auto-generated).
     */
    public static function formatRespondentDisplay(?string $respondentNumber): string
    {
        if (! $respondentNumber || $respondentNumber === '—') {
            return 'Auto-generated';
        }

        if (strpos($respondentNumber, '-') !== false) {
            $last = substr($respondentNumber, strrpos($respondentNumber, '-') + 1);

            return (string) ((int) $last);
        }

        if (is_numeric($respondentNumber)) {
            return (string) ((int) $respondentNumber);
        }

        return (string) $respondentNumber;
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

        $request->merge([
            'last_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('last_name', ''))) ?: '',
            'first_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('first_name', ''))) ?: '',
            'middle_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('middle_name', ''))) ?: null,
        ]);

        if ($request->input('middle_name') === '') {
            $request->merge(['middle_name' => null]);
        }

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'first_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'middle_name' => ['nullable', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'suffix' => ['required', 'string', 'in:None,Jr.,Sr.,I,II,III,IV,V,Others'],
            'custom_suffix' => ['nullable', 'required_if:suffix,Others', 'string', 'max:5', 'regex:/^(?!\s+$)[A-Za-z.\s]+$/'],
            'purok_zone' => $this->barangayZoneService->purokZoneRules((int) $registration->barangay_id),
            'sex' => 'required|in:Male,Female',
            'age' => 'required|integer|min:15|max:30',
            'birthday' => 'required|date|before_or_equal:today',
            'email' => ValidEmailAddress::profilingRules(),
            'contact_number' => ['required', 'string', 'max:30', new PhilippineMobileNumber((int) $registration->id)],
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
            'signature_name' => [
                'required',
                'string',
                'min:'.(int) config('signature.name_min', 1),
                'max:'.(int) config('signature.name_max', 255),
            ],
            'signature' => ['required', 'string', new ParticipantSignatureImage],
        ], [
            'contact_number.required' => PhoneNumberService::MSG_REQUIRED,
            'signature_name.required' => config('signature.messages.name_required'),
            'signature_name.min' => config('signature.messages.name_required'),
            'signature_name.max' => config('signature.messages.name_max'),
            'signature.required' => config('signature.messages.required'),
        ] + ValidEmailAddress::profilingMessages());

        $localContact = app(PhoneNumberService::class)->toLocalMobile($validated['contact_number'] ?? null);
        if ($localContact === null) {
            return $this->updateErrorResponse($request, [
                'contact_number' => PhoneNumberService::MSG_INVALID,
            ]);
        }
        $validated['contact_number'] = $localContact;

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
        // Respondent # is assigned only after a successful update completes.
        unset($validated['respondent_number']);

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
        session()->put('kk_profiling_update_required', false);

        return $this->updateSuccessResponse($request, [
            'title' => 'Congratulations!',
            'message' => "You've successfully updated your KK Profiling for {$profilingYear}.",
            'profiling_year' => $profilingYear,
            'notification' => [
                'title' => 'KK Profiling Updated',
                'message' => "Congratulations! You've successfully updated your KK Profiling for {$profilingYear} in {$barangayName}.",
            ],
        ]);
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
        $validated['group_chat'] = null;
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
            app(RespondentNumberService::class)->assignToRegistration($registration);
            $registration->refresh();

            app(KabataanProfilingHistoryService::class)->saveSnapshot(
                $registration,
                $profilingYear,
                $scheduleId,
            );

            $scheduleService = app(KkProfilingScheduleService::class);
            if (\Illuminate\Support\Facades\Schema::hasTable('kk_profiling_updates')) {
                $scheduleService->markAnnualUpdateCompleted($registration, $profilingYear);
            }
            $scheduleService->forgetRegistrationCaches(
                $registration,
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
            $this->invalidEmails->recordFailureFromException($email, $e);
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

        return redirect()->route('dashboard');
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

        $request->merge([
            'last_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('last_name', ''))) ?: '',
            'first_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('first_name', ''))) ?: '',
            'middle_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('middle_name', ''))) ?: null,
        ]);

        if ($request->input('middle_name') === '') {
            $request->merge(['middle_name' => null]);
        }

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'first_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'middle_name' => ['nullable', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'suffix' => ['required', 'string', 'in:None,Jr.,Sr.,I,II,III,IV,V,Others'],
            'custom_suffix' => ['nullable', 'required_if:suffix,Others', 'string', 'max:5', 'regex:/^(?!\s+$)[A-Za-z.\s]+$/'],
            'purok_zone' => $this->barangayZoneService->purokZoneRules((int) $barangayRecord->id),
            'sex' => 'required|in:Male,Female',
            'age' => 'required|integer|min:15|max:30',
            'birthday' => 'required|date|before_or_equal:today',
            'email' => ValidEmailAddress::profilingRules(),
            'contact_number' => ['required', 'string', 'max:30', new PhilippineMobileNumber],
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
            'signature_name' => [
                'required',
                'string',
                'min:'.(int) config('signature.name_min', 1),
                'max:'.(int) config('signature.name_max', 255),
            ],
            'signature' => ['required', 'string', new ParticipantSignatureImage],
        ], [
            'contact_number.required' => PhoneNumberService::MSG_REQUIRED,
            'signature_name.required' => config('signature.messages.name_required'),
            'signature_name.min' => config('signature.messages.name_required'),
            'signature_name.max' => config('signature.messages.name_max'),
            'signature.required' => config('signature.messages.required'),
        ] + ValidEmailAddress::profilingMessages());

        $localContact = app(PhoneNumberService::class)->toLocalMobile($validated['contact_number'] ?? null);
        if ($localContact === null) {
            return $this->submitErrorResponse($request, [
                'contact_number' => PhoneNumberService::MSG_INVALID,
            ]);
        }
        $validated['contact_number'] = $localContact;

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
        $validated['group_chat'] = null;

        \Log::info('Validation passed');

        $email = strtolower(trim($validated['email']));

        $invalidCheck = $this->invalidEmails->checkBeforeSending($email);
        if (! $invalidCheck['allowed']) {
            return $this->submitErrorResponse($request, [
                'email' => $invalidCheck['message'] ?? 'This email address is invalid and cannot receive mail.',
            ]);
        }

        // Check for active pending registration
        $activePendingRegistration = KabataanRegistration::where('email', $email)
            ->where('barangay_id', $barangayRecord->id)
            ->whereIn('status', ['pending_verification', 'email_verified', 'password_set', 'pending'])
            ->whereNull('deleted_at')
            ->first();

        // Check for active approved registration
        $approvedRegistration = KabataanRegistration::where('email', $email)
            ->where('barangay_id', $barangayRecord->id)
            ->whereIn('status', ['active', 'approved'])
            ->whereNull('deleted_at')
            ->first();

        if ($approvedRegistration) {
            return $this->submitErrorResponse($request, [
                'email' => 'This email is already taken. Please use another email.',
            ]);
        }

        if ($activePendingRegistration) {
            return $this->submitErrorResponse($request, [
                'registration' => 'You already have a KK Profiling application under review. Please wait for the SK Official\'s review.',
            ]);
        }

        // Find previous rejected application if any to link as re-application
        $previousRejected = KabataanRegistration::where('email', $email)
            ->where('barangay_id', $barangayRecord->id)
            ->where('status', 'rejected')
            ->latest('id')
            ->first();

        $previousApplicationId = $previousRejected?->id;
        $userId = $previousRejected?->user_id;

        if (! $userId) {
            $userByEmail = User::where('email', $email)->first();
            $userId = $userByEmail?->id;
        }

        // Create a NEW application record (never overwrite rejected applications)
        $registration = KabataanRegistration::create([
            'tenant_id' => $barangayRecord->tenant_id,
            'barangay_id' => $barangayRecord->id,
            'user_id' => $userId,
            'previous_application_id' => $previousApplicationId,
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'suffix' => $validated['suffix'] ?? null,
            'email' => $validated['email'],
            'contact_number' => $validated['contact_number'] ?? null,
            'profile_photo_path' => null,
            'form_data' => $validated,
            'status' => 'pending_verification',
            'evaluation_status' => null,
            'evaluation_notes' => null,
            'review_notes' => null,
            'profiling_year' => $validated['profile_updated_year'] ?? now()->year,
            'submitted_at' => now(),
        ]);

        try {
            (new KkSurveyResponseService)->syncFromRegistration($registration->fresh(), 'pending');
        } catch (\Throwable $e) {
            report($e);
        }

        // Send verification email
        try {
            $this->sendVerificationEmail($registration);
        } catch (ValidationException $e) {
            return $this->submitErrorResponse($request, $e->errors());
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
        $request->validate(
            [
                'email' => ValidEmailAddress::profilingRules(),
                'current_email' => ['nullable', 'string', 'max:'.ValidEmailAddress::MAX_LENGTH],
            ],
            ValidEmailAddress::profilingMessages()
        );

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

        // Check for active pending or approved registrations
        $activeRegistration = KabataanRegistration::query()
            ->where('email', $email)
            ->whereIn('status', ['active', 'approved', 'pending_verification', 'password_set', 'pending', 'email_verified'])
            ->whereNull('deleted_at')
            ->first();

        if ($activeRegistration) {
            $isPending = in_array($activeRegistration->status, ['pending_verification', 'password_set', 'pending', 'email_verified'], true);
            $msg = $isPending
                ? 'You already have a KK Profiling application under review. Please wait for the SK Official\'s review.'
                : 'This email is already taken. Please use another email.';

            return response()->json([
                'exists' => true,
                'message' => $msg,
            ]);
        }

        // If user only has a rejected registration, allow re-application
        $hasRejected = KabataanRegistration::query()
            ->where('email', $email)
            ->where('status', 'rejected')
            ->exists();

        if ($hasRejected) {
            return response()->json([
                'exists' => false,
                'is_reapplication' => true,
                'message' => null,
            ]);
        }

        $existingApprovedUser = User::where('email', $email)
            ->where('status', 'ACTIVE')
            ->exists();

        return response()->json([
            'exists' => $existingApprovedUser,
            'message' => $existingApprovedUser ? 'This email is already taken. Please use another email.' : null,
        ]);
    }

    /**
     * Resend KK Profiling email verification link.
     */
    public function resendVerification(Request $request)
    {
        if ($fail = $this->turnstileGuard->enforce(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request)) {
            return response()->json([
                'success' => false,
                'message' => $fail,
                'turnstile_required' => true,
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
            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request);

            return response()->json([
                'success' => false,
                'message' => 'Unable to resend verification for this request. Please check your details and try again.',
                'turnstile_required' => (bool) $attempt['turnstile_required'],
            ], 404);
        }

        if ($registration->status !== 'pending_verification') {
            return response()->json([
                'success' => false,
                'message' => 'This email has already been verified or registration is complete.',
                'turnstile_required' => $this->turnstileGuard->isRequired(
                    TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY,
                    $request
                ),
            ], 422);
        }

        try {
            $this->sendVerificationEmail($registration);

            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request);

            return response()->json([
                'success' => true,
                'message' => 'Verification email has been resent. Please check your inbox.',
                'turnstile_required' => (bool) $attempt['turnstile_required'],
            ]);
        } catch (ValidationException $e) {
            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request);

            return response()->json([
                'success' => false,
                'message' => $e->errors()['email'][0] ?? 'This email address is invalid and cannot receive mail.',
                'errors' => $e->errors(),
                'turnstile_required' => (bool) $attempt['turnstile_required'],
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to resend verification email', [
                'email' => $registration->email,
                'error' => $e->getMessage(),
            ]);

            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY, $request);

            return response()->json([
                'success' => false,
                'message' => $attempt['message'] ?? 'Failed to send verification email. Please try again later.',
                'turnstile_required' => (bool) $attempt['turnstile_required'],
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
            'turnstileRequired' => $this->turnstileGuard->isRequired(
                TurnstileAttemptGuard::ACTION_KK_EMAIL_VERIFY,
                $request
            ),
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

        $this->invalidEmails->attemptMailDelivery((string) $registration->email, function () use ($registration, $verificationUrl) {
            Notification::route('mail', $registration->email)
                ->notify(new KabataanVerifyEmail($verificationUrl));
        }, 'email');
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
            'barangay' => $registration->barangay?->name ?? 'Barangay',
            'slug' => $barangay,
            'email' => $registration->email,
            'registrationAlreadyComplete' => true,
            'registrationAutoApproved' => RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status),
            'barangayLogoUrl' => self::getBarangayLogoUrl($registration->barangay_id),
        ]);
        }

        return view('kkprofiling::set_password', [
            'barangay' => $registration->barangay?->name ?? 'Barangay',
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
