<?php

namespace App\Modules\Programs\Services;

use App\Models\Abyip;
use App\Models\KabataanRegistration;
use App\Models\KkSurveyResponse;
use App\Models\ProgramApplication;
use App\Models\ScheduleProgram;
use App\Models\ScholarshipQuickGuidelineStep;
use App\Models\User;
use App\Modules\KKProfiling\Controllers\KKProfilingController;
use App\Modules\Profile\Services\ProfileImageService;
use App\Services\ScholarshipSystemFieldsService;
use App\Services\SkOfficialsNotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class KabataanProgramService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly ProgramDocumentService $documentService,
        private readonly KabataanProgramSurveyService $surveyService,
        private readonly KabataanProgramEvaluationService $evaluationService,
        private readonly ScholarshipSystemFieldsService $scholarshipSystemFields,
        private readonly ProfileImageService $profileImageService,
    ) {}

    /** @var list<string> */
    private const YOUTH_PROGRAM_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

    /** @var list<string> */
    private const SCHOLARSHIP_EDUCATION_LEVELS = ['High School Level', 'College Level'];

    /** @var list<string> */
    private const ELIGIBILITY_YOUTH_CLASSIFICATIONS = [
        'In School Youth',
        'Out of School Youth',
        'Working Youth',
        'Person w/ Disability',
        'Children in Conflict w/ Law',
        'Indigenous People',
    ];

    /** @var list<string> */
    private const ELIGIBILITY_YOUTH_AGE_GROUPS = [
        'Child Youth (15-17 yrs old)',
        'Core Youth (18-24 yrs old)',
        'Young Adult (15-30 yrs old)',
    ];

    /**
     * @var array<string, array{category_key: string, modal_key: string, type: string, emoji: string, short_label: string}>
     */
    private const LETTER_META = [
        'A' => ['category_key' => 'education', 'modal_key' => 'education', 'type' => 'education', 'emoji' => '🎓', 'short_label' => 'Education'],
        'B' => ['category_key' => 'environment', 'modal_key' => 'environment', 'type' => 'other', 'emoji' => '🌿', 'short_label' => 'Environment'],
        'C' => ['category_key' => 'disaster', 'modal_key' => 'disaster', 'type' => 'other', 'emoji' => '🛡️', 'short_label' => 'Disaster Preparedness'],
        'D' => ['category_key' => 'agriculture', 'modal_key' => 'agriculture', 'type' => 'other', 'emoji' => '🌱', 'short_label' => 'Livelihood'],
        'E' => ['category_key' => 'health', 'modal_key' => 'health', 'type' => 'other', 'emoji' => '❤️', 'short_label' => 'Health'],
        'F' => ['category_key' => 'anti-drugs', 'modal_key' => 'anti-drugs', 'type' => 'other', 'emoji' => '🚫', 'short_label' => 'Anti-Drugs'],
        'G' => ['category_key' => 'gender', 'modal_key' => 'gender', 'type' => 'other', 'emoji' => '💜', 'short_label' => 'Gender and Development'],
        'H' => ['category_key' => 'feeding', 'modal_key' => 'others', 'type' => 'other', 'emoji' => '🍽️', 'short_label' => 'Feeding'],
        'I' => ['category_key' => 'sports', 'modal_key' => 'sports', 'type' => 'sports', 'emoji' => '⚽', 'short_label' => 'Sports Development'],
        'J' => ['category_key' => 'others', 'modal_key' => 'others', 'type' => 'other', 'emoji' => '📋', 'short_label' => 'Others'],
    ];

    /** @var array<string, string> */
    private const KK_FIELD_LABELS = [
        'last_name' => 'Last Name',
        'first_name' => 'First Name',
        'middle_name' => 'Middle Name',
        'suffix' => 'Suffix',
        'full_name' => 'Full Name',
        'birthday' => 'Birthday',
        'age' => 'Age',
        'sex' => 'Sex',
        'civil_status' => 'Civil Status',
        'contact_number' => 'Contact Number',
        'email' => 'Email Address',
        'region' => 'Region',
        'province' => 'Province',
        'city' => 'City/Municipality',
        'barangay' => 'Barangay',
        'purok_zone' => 'Purok / Zone',
        'youth_classification' => 'Youth Classification',
        'youth_age_group' => 'Youth Age Group',
        'education' => 'Education',
        'current_school' => 'Current School',
        'course_strand' => 'Course / Strand',
        'work_status' => 'Work Status',
        'sk_voter' => 'Registered SK Voter',
        'sk_voted' => 'Voted in Last SK Election',
        'home_address' => 'Home Address',
        'year_level' => 'Year Level',
        'city_municipality' => 'City/Municipality',
    ];

    public function getLatestAbyipDocument(?int $barangayId): ?Abyip
    {
        if ($barangayId === null) {
            return null;
        }

        return Cache::remember("abyip.latest_document.{$barangayId}", self::CACHE_TTL, function () use ($barangayId) {
            return Abyip::query()
                ->documents()
                ->where('barangay_id', $barangayId)
                ->orderByDesc('fiscal_year')
                ->orderByDesc('id')
                ->first();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardPayload(User $user): array
    {
        return Cache::remember("kabataan_dashboard_payload_v3_u{$user->id}", self::CACHE_TTL, function () use ($user) {
            $barangayId = $this->resolveUserBarangayId($user);
            $document = $this->getLatestAbyipDocument($barangayId);

            $abyipPrograms = [];
            if ($document !== null) {
                $programModels = Abyip::query()
                    ->where('document_id', $document->id)
                    ->where('row_type', Abyip::ROW_YOUTH_PROGRAM)
                    ->whereNull('parent_id')
                    ->whereIn('code', self::YOUTH_PROGRAM_LETTERS)
                    ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->unique(fn (Abyip $program) => strtoupper(trim((string) ($program->program_letter ?? $program->code ?? ''))))
                    ->values();

                $programIds = $programModels->pluck('id')->map(fn ($id) => (int) $id)->all();

                $openSurveyMap = $this->surveyService->summarizeOpenSurveysForPrograms($user, $programIds);
                $latestSurveyMap = $this->surveyService->summarizeLatestSurveysForPrograms($user, $programIds);
                $openEvaluationMap = $this->evaluationService->summarizeOpenEvaluationsForPrograms($user, $programIds);

                $abyipPrograms = $programModels
                    ->map(function (Abyip $program) use ($user, $openSurveyMap, $latestSurveyMap, $openEvaluationMap) {
                        $programId = (int) $program->id;

                        return $this->formatAbyipProgram(
                            $program,
                            $user,
                            $openSurveyMap[$programId] ?? null,
                            $latestSurveyMap[$programId] ?? null,
                            $openEvaluationMap[$programId] ?? null,
                        );
                    })
                    ->values()
                    ->all();
            }

            $schedulePrograms = $this->scheduleProgramsQuery($user)
                ->get()
                ->map(fn (ScheduleProgram $program) => $this->formatScheduleProgram($program, $user))
                ->values()
                ->all();

            $hasScholarshipApplicationHistory = ProgramApplication::query()
                ->where('kabataan_id', $user->id)
                ->whereNot('status', ProgramApplication::STATUS_CANCELLED)
                ->whereHas('scheduleProgram', fn ($query) => $query->where('program_letter', 'A'))
                ->exists();

            return [
                'calendar_year' => $document?->fiscal_year,
                'abyip_programs' => $abyipPrograms,
                'schedule_programs' => $schedulePrograms,
                'has_scholarship_application_history' => $hasScholarshipApplicationHistory,
                'pending_evaluations' => $this->evaluationService->listPendingEvaluationsForUser($user),
            ];
        });
    }

    public function clearUserDashboardCache(User $user): void
    {
        Cache::forget("kabataan_dashboard_payload_u{$user->id}");
        Cache::forget("kabataan_dashboard_payload_v2_u{$user->id}");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getScheduleProgramForUser(int $scheduleProgramId, User $user): ?array
    {
        $program = $this->scheduleProgramsQuery($user)
            ->where('id', $scheduleProgramId)
            ->first();

        if ($program === null) {
            return null;
        }

        $application = $this->findUserApplication($user->id, $scheduleProgramId);
        $formatted = $this->formatScheduleProgram($program, $user, true);
        $formatted['kk_profile'] = $this->resolveKkProfile($user, $program->kk_profiling_fields ?? []);
        $formatted['profile_image_url'] = $this->profileImageService->resolveDisplayUrl(
            $user,
            $formatted['kk_profile']['full_name'] ?? null
        );
        $formatted['application'] = $this->formatUserApplication($application, true, $user);
        $formatted['uploaded_documents'] = $this->resolveUploadedDocuments(
            $user,
            $scheduleProgramId,
            $program->custom_questions ?? [],
            $application,
        );

        $eligibility = $this->evaluateEligibility($user, $program);
        $periodOpen = $this->isSchedulePeriodOpen($program);
        $effectiveOpen = $program->status === ScheduleProgram::STATUS_OPEN && $periodOpen;
        $formatted['can_apply'] = $eligibility['eligible'] && $effectiveOpen;
        $formatted['eligibility_message'] = ! $effectiveOpen
            ? 'Application period has ended.'
            : $eligibility['message'];

        return $formatted;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUserApplications(User $user, bool $withAnswers = false, ?string $letter = null): array
    {
        $query = ProgramApplication::query()
            ->with(['scheduleProgram'])
            ->where('kabataan_id', $user->id);

        if ($letter !== null && trim($letter) !== '') {
            $letter = strtoupper(trim($letter));
            $query->whereHas('scheduleProgram', function ($builder) use ($letter) {
                $builder->where('program_letter', $letter);
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ProgramApplication $app) => $this->formatUserApplication($app, $withAnswers, $withAnswers ? $user : null))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserApplication(User $user, int $applicationId): array
    {
        $application = ProgramApplication::query()
            ->with(['scheduleProgram'])
            ->whereKey($applicationId)
            ->where('kabataan_id', $user->id)
            ->first();

        if ($application === null) {
            throw ValidationException::withMessages([
                'application_id' => ['Application not found.'],
            ]);
        }

        return $this->formatUserApplication($application, true, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelApplication(User $user, int $applicationId, string $cancelReason): array
    {
        $application = ProgramApplication::query()
            ->with(['scheduleProgram'])
            ->whereKey($applicationId)
            ->where('kabataan_id', $user->id)
            ->first();

        if ($application === null) {
            throw ValidationException::withMessages([
                'application_id' => ['Application not found.'],
            ]);
        }

        if ($application->status !== ProgramApplication::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'application_id' => ['Only pending applications can be cancelled.'],
            ]);
        }

        $reason = trim($cancelReason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancel_reason' => ['Please provide a reason for cancelling your application.'],
            ]);
        }

        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'cancel_reason' => ['Cancel reason must not exceed 500 characters.'],
            ]);
        }

        $application->update([
            'status' => ProgramApplication::STATUS_CANCELLED,
            'cancel_reason' => $reason,
        ]);

        return $this->formatUserApplication($application->fresh(['scheduleProgram']), true);
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @return array<string, mixed>
     */
    public function submitApplication(User $user, int $scheduleProgramId, array $answers, array $systemFieldAnswers = []): array
    {
        $program = $this->scheduleProgramsQuery($user)
            ->where('id', $scheduleProgramId)
            ->first();

        if ($program === null) {
            throw ValidationException::withMessages([
                'schedule_program_id' => ['Program not found or not available.'],
            ]);
        }

        if ($program->status !== ScheduleProgram::STATUS_OPEN || ! $this->isSchedulePeriodOpen($program)) {
            throw ValidationException::withMessages([
                'schedule_program_id' => ['This program is no longer accepting applications.'],
            ]);
        }

        $eligibility = $this->evaluateEligibility($user, $program);
        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages([
                'schedule_program_id' => [$eligibility['message']],
            ]);
        }

        $activeApplication = $this->findUserApplication($user->id, $scheduleProgramId);
        if ($activeApplication !== null) {
            throw ValidationException::withMessages([
                'schedule_program_id' => ['You have already applied for this program.'],
            ]);
        }

        $questions = collect($program->custom_questions ?? []);
        $isSports = strtoupper((string) $program->program_letter) === 'I';
        $questionsToValidate = $isSports
            ? $questions->values()->all()
            : $questions->filter(fn ($q) => ($q['type'] ?? '') === 'file')->values()->all();
        $this->validateAnswers($questionsToValidate, $answers);

        if ($isSports) {
            $this->validateSportsTeamCapacity($program, $answers);
        }

        $registration = KabataanRegistration::query()
            ->with('barangay')
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->first();

        if ($registration === null) {
            throw ValidationException::withMessages([
                'kabataan_id' => ['KK Profiling registration is required before applying.'],
            ]);
        }

        $customAnswers = $this->buildCustomAnswersPayload(
            $answers,
            $isSports ? $questions : $questions->filter(fn ($q) => ($q['type'] ?? '') === 'file')
        );
        $profileData = $this->buildApplicationProfileData($user, $registration);
        $kkProfile = $this->resolveKkProfile($user, ['education', 'birthday', 'age']);
        $kkEducation = trim((string) ($kkProfile['education'] ?? ''));

        if ($isSports) {
            $validatedSystemFields = [];
            $matchedClassification = $eligibility['matched_classification'] ?? null;
            if (is_array($matchedClassification)) {
                $validatedSystemFields['sports_classification'] = $matchedClassification['name'] ?? null;
                $validatedSystemFields['sports_classification_id'] = $matchedClassification['id'] ?? null;
            }
        } else {
            $validatedSystemFields = $this->scholarshipSystemFields->validate(
                $systemFieldAnswers,
                $kkEducation,
                [
                    'birthday' => $kkProfile['birthday'] ?? null,
                    'age' => is_numeric($kkProfile['age'] ?? null) ? (int) $kkProfile['age'] : null,
                ],
            );
        }

        $profileData = $this->mergeSystemFieldsIntoProfile($profileData, $validatedSystemFields);
        $existingRecord = $this->findUserApplicationRecord($user->id, $scheduleProgramId);
        $isResubmit = $existingRecord !== null
            && $existingRecord->status === ProgramApplication::STATUS_CANCELLED;

        if ($existingRecord !== null && ! $isResubmit) {
            throw ValidationException::withMessages([
                'schedule_program_id' => ['You have already applied for this program.'],
            ]);
        }

        $applicationPayload = array_merge($profileData, [
            'program_id' => $program->id,
            'kabataan_id' => $user->id,
            'custom_answers' => $customAnswers,
            'system_field_answers' => $validatedSystemFields,
            'status' => ProgramApplication::STATUS_PENDING,
            'cancel_reason' => null,
            'rejection_reason' => null,
            'rejection_reasons' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        if ($isResubmit) {
            $existingRecord->update($applicationPayload);
            $application = $existingRecord->fresh();
        } else {
            $application = ProgramApplication::query()->create($applicationPayload);
        }

        try {
            $application->update([
                'required_documents' => $this->documentService->finalizeDraftDocuments($user, $application, $answers),
            ]);
        } catch (\Throwable $exception) {
            if (! $isResubmit) {
                $application->delete();
            }

            throw $exception;
        }

        $application->load(['scheduleProgram']);

        try {
            (new SkOfficialsNotificationDispatcher)->notifyProgramApplication(
                (int) ($user->barangay_id ?? $program->barangay_id ?? 0),
                (string) ($registration->full_name ?? $user->name ?? 'A Kabataan member'),
                (string) ($program->program_name ?? 'a program'),
                $program->program_letter ?? null,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->formatUserApplication($application, true);
    }

    private function scheduleProgramsQuery(User $user)
    {
        $tenantId = $this->resolveUserTenantId($user);
        $barangayId = $this->resolveUserBarangayId($user);

        return ScheduleProgram::query()
            ->with('quickGuidelineSteps')
            ->active()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($barangayId !== null, fn ($q) => $q->where('barangay_id', $barangayId))
            ->where('status', ScheduleProgram::STATUS_OPEN)
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    private function isSchedulePeriodOpen(ScheduleProgram $program): bool
    {
        if ($program->end_date === null) {
            return true;
        }

        return ! Carbon::today()->gt($program->end_date->copy()->startOfDay());
    }

    private function findUserApplication(int $userId, int $scheduleProgramId): ?ProgramApplication
    {
        return ProgramApplication::query()
            ->with(['scheduleProgram'])
            ->where('kabataan_id', $userId)
            ->where('program_id', $scheduleProgramId)
            ->whereNot('status', ProgramApplication::STATUS_CANCELLED)
            ->first();
    }

    private function findUserApplicationRecord(int $userId, int $scheduleProgramId): ?ProgramApplication
    {
        return ProgramApplication::query()
            ->where('kabataan_id', $userId)
            ->where('program_id', $scheduleProgramId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAbyipProgram(
        Abyip $program,
        User $user,
        ?array $openSurvey = null,
        ?array $latestSurvey = null,
        ?array $openEvaluation = null,
    ): array {
        $letter = strtoupper(trim((string) ($program->program_letter ?? $program->code ?? '')));
        $meta = self::LETTER_META[$letter] ?? [
            'category_key' => 'others',
            'modal_key' => 'others',
            'type' => 'other',
            'emoji' => '📋',
            'short_label' => 'Program',
        ];

        $activities = $program->children
            ->map(fn ($activity) => trim((string) $activity->program_name))
            ->filter()
            ->values()
            ->all();

        $description = trim((string) ($program->description ?? ''));
        if ($description === '' && $activities !== []) {
            $description = 'Activities: '.implode(', ', $activities);
        }
        if ($description === '') {
            $description = 'No activities listed.';
        }

        $tenantId = $this->resolveUserTenantId($user);
        $barangayId = $this->resolveUserBarangayId($user);

        $scheduleCount = ScheduleProgram::query()
            ->active()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($barangayId !== null, fn ($q) => $q->where('barangay_id', $barangayId))
            ->where('status', ScheduleProgram::STATUS_OPEN)
            ->where('program_type', trim((string) $program->program_name))
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', Carbon::today());
            })
            ->count();

        if ($openSurvey === null) {
            $openSurvey = $this->surveyService->summarizeOpenSurveyForProgram($user, (int) $program->id);
        }

        if ($latestSurvey === null && $openSurvey !== null) {
            $latestSurvey = $openSurvey;
        }

        $hasSurvey = $latestSurvey !== null;
        $surveyForDisplay = $openSurvey ?? $latestSurvey;

        if ($meta['type'] === 'education' || $meta['type'] === 'sports') {
            $activeCount = $scheduleCount;
        } else {
            $activeCount = ($hasSurvey && ($openSurvey['can_respond'] ?? false)) ? 1 : 0;
            if (($openEvaluation['can_respond'] ?? false)) {
                $activeCount = 1;
            }
        }

        return [
            'id' => $program->id,
            'letter' => $letter,
            'title' => $program->program_name,
            'description' => $description,
            'activities' => $activities,
            'category_key' => $meta['category_key'],
            'modal_key' => $meta['modal_key'],
            'type' => $meta['type'],
            'emoji' => $meta['emoji'],
            'short_label' => $meta['short_label'],
            'active_count' => $activeCount,
            'schedule_count' => $scheduleCount,
            'has_survey' => $hasSurvey,
            'survey' => $surveyForDisplay,
            'has_evaluation' => $openEvaluation !== null,
            'evaluation' => $openEvaluation,
        ];
    }

    private function resolveUserBarangayId(User $user): ?int
    {
        if ($user->barangay_id !== null) {
            return (int) $user->barangay_id;
        }

        $barangayId = KabataanRegistration::query()
            ->where('user_id', $user->id)
            ->latest()
            ->value('barangay_id');

        return $barangayId !== null ? (int) $barangayId : null;
    }

    private function resolveUserTenantId(User $user): ?int
    {
        if ($user->tenant_id !== null) {
            return (int) $user->tenant_id;
        }

        $registration = KabataanRegistration::query()
            ->with('barangay')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $tenantId = $registration?->barangay?->tenant_id;

        return $tenantId !== null ? (int) $tenantId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatScheduleProgram(ScheduleProgram $program, User $user, bool $includeQuestions = false): array
    {
        $application = $this->findUserApplication($user->id, $program->id);

        $availableSlots = $this->calculateAvailableSlots($program);

        $sportsDetails = is_array($program->sports_details) ? $program->sports_details : [];

        $scholarshipDetails = is_array($program->scholarship_details) ? $program->scholarship_details : [];
        unset($scholarshipDetails['quick_guidelines']);
        $quickGuidelines = $this->resolveQuickGuidelinesForProgram($program);
        if ($quickGuidelines !== []) {
            $scholarshipDetails['quick_guidelines'] = $quickGuidelines;
        }

        if ($scholarshipDetails !== []) {
            $submission = is_array($scholarshipDetails['submission_period'] ?? null)
                ? $scholarshipDetails['submission_period']
                : null;
            if ($submission !== null) {
                if (! empty($submission['start'])) {
                    $scholarshipDetails['submission_period']['start_display'] = $this->formatDate(
                        \Carbon\Carbon::parse((string) $submission['start'])
                    );
                }
                if (! empty($submission['end'])) {
                    $scholarshipDetails['submission_period']['end_display'] = $this->formatDate(
                        \Carbon\Carbon::parse((string) $submission['end'])
                    );
                }
            }

            $verification = is_array($scholarshipDetails['verification_period'] ?? null)
                ? $scholarshipDetails['verification_period']
                : null;
            if ($verification !== null) {
                if (! empty($verification['start'])) {
                    $scholarshipDetails['verification_period']['start_display'] = $this->formatDate(
                        \Carbon\Carbon::parse((string) $verification['start'])
                    );
                }
                if (! empty($verification['end'])) {
                    $scholarshipDetails['verification_period']['end_display'] = $this->formatDate(
                        \Carbon\Carbon::parse((string) $verification['end'])
                    );
                }
            }
        }

        $periodOpen = $this->isSchedulePeriodOpen($program);
        $effectiveStatus = ($program->status === ScheduleProgram::STATUS_OPEN && $periodOpen)
            ? ScheduleProgram::STATUS_OPEN
            : ScheduleProgram::STATUS_CLOSED;

        $payload = [
            'id' => $program->id,
            'program_type' => $program->program_type,
            'program_name' => $program->program_name,
            'program_letter' => $program->program_letter,
            'committee' => $program->committee,
            'participation_quantity' => $program->participation_quantity,
            'available_slots' => $availableSlots,
            'submitted_applications_count' => $this->countActiveApplications($program->id),
            'start_date' => $program->start_date?->format('Y-m-d'),
            'start_date_display' => $this->formatDate($program->start_date),
            'end_date' => $program->end_date?->format('Y-m-d'),
            'end_date_display' => $this->formatDate($program->end_date),
            'status' => $effectiveStatus,
            'is_period_open' => $periodOpen,
            'announcement' => $program->announcement,
            'scholarship_details' => $scholarshipDetails !== [] ? $scholarshipDetails : $program->scholarship_details,
            'sports_details' => $program->sports_details,
            'sport_key' => $sportsDetails['sport_key'] ?? null,
            'sport_label' => $this->resolveSportLabel($sportsDetails),
            'kk_profiling_fields' => $program->kk_profiling_fields ?? [],
            'custom_questions' => $program->custom_questions ?? [],
            'has_applied' => $application !== null,
            'application_status' => $application?->status,
            'application_id' => $application?->id,
            'quick_guidelines' => $quickGuidelines,
        ];

        $eligibility = $this->evaluateEligibility($user, $program);
        $payload['can_apply'] = $eligibility['eligible'] && $effectiveStatus === ScheduleProgram::STATUS_OPEN;
        $payload['eligibility_message'] = $effectiveStatus !== ScheduleProgram::STATUS_OPEN
            ? 'Application period has ended.'
            : $eligibility['message'];

        if (strtoupper((string) $program->program_letter) === 'I') {
            $payload['eligible_classifications'] = $eligibility['eligible_classifications'] ?? [];
            $payload['matched_classification'] = $eligibility['matched_classification'] ?? null;
            $payload['kk_age'] = $this->resolveUserAge($user);
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @return list<array{label: string, value: string}>
     */
    private function formatAnswersPreview(array $answers): array
    {
        return collect($answers)
            ->map(function (array $answer) {
                $label = trim((string) ($answer['question_label'] ?? 'Answer'));
                $value = $answer['answer'] ?? '';

                if (is_array($value)) {
                    if (isset($value['original_name'])) {
                        $value = (string) $value['original_name'];
                    } else {
                        $value = implode(', ', array_map('strval', $value));
                    }
                }

                $value = trim((string) $value);
                if ($value === '') {
                    $value = '—';
                } elseif (mb_strlen($value) > 120) {
                    $value = mb_substr($value, 0, 117).'...';
                }

                return [
                    'label' => $label !== '' ? $label : 'Answer',
                    'value' => $value,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatUserApplication(?ProgramApplication $application, bool $withAnswers = false, ?User $user = null): ?array
    {
        if ($application === null) {
            return null;
        }

        $displayDate = $application->updated_at && $application->created_at
            && $application->updated_at->gt($application->created_at)
            ? $application->updated_at
            : $application->created_at;

        $scheduleProgram = $application->scheduleProgram;

        $sportsDetails = is_array($scheduleProgram?->sports_details) ? $scheduleProgram->sports_details : [];

        $payload = [
            'id' => $application->id,
            'schedule_program_id' => $application->program_id,
            'program_letter' => $scheduleProgram?->program_letter,
            'program_name' => $scheduleProgram?->program_name ?? 'Program',
            'program_type' => $scheduleProgram?->program_type,
            'committee' => $scheduleProgram?->committee,
            'sport_key' => $sportsDetails['sport_key'] ?? null,
            'sport_label' => $this->resolveSportLabel($sportsDetails),
            'program_period' => $this->formatProgramPeriod($scheduleProgram),
            'status' => $application->status,
            'status_display' => match ((string) $application->status) {
                ProgramApplication::STATUS_APPROVED => 'Completed — Approved',
                ProgramApplication::STATUS_REJECTED => 'Rejected',
                ProgramApplication::STATUS_CANCELLED => 'Cancelled',
                ProgramApplication::STATUS_PENDING => 'Pending Review',
                default => ucfirst((string) $application->status),
            },
            'submitted_at' => $displayDate?->format('M j, Y'),
            'submitted_at_iso' => $displayDate?->toIso8601String(),
            'application_year' => $displayDate?->format('Y')
                ?? $scheduleProgram?->start_date?->format('Y')
                ?? now()->format('Y'),
            'cancel_reason' => $application->cancel_reason,
            'can_cancel' => $application->status === ProgramApplication::STATUS_PENDING,
            'team_name' => $this->extractTeamNameFromApplication($application),
            'answers_preview' => $this->formatAnswersPreview($application->custom_answers ?? []),
        ];

        if ($withAnswers) {
            if ($user !== null) {
                $payload['personal_info'] = $this->formatApplicationPersonalInfo($application, $user);
                $registration = KabataanRegistration::query()
                    ->where('user_id', $user->id)
                    ->latest()
                    ->first();
                $respondentNumber = $registration?->respondent_number
                    ?? ($registration?->form_data['respondent_number'] ?? null);
                $payload['respondent_number'] = $respondentNumber;
                $payload['respondent_display'] = KKProfilingController::formatRespondentDisplay($respondentNumber);
            }

            $documents = $this->documentService->listApplicationDocuments($application);

            $payload['answers'] = collect($application->custom_answers ?? [])
                ->map(function (array $answer) use ($documents) {
                    $questionId = (string) ($answer['question_id'] ?? '');
                    $decoded = $answer['answer'] ?? null;
                    if (is_string($decoded)) {
                        $parsed = json_decode($decoded, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $decoded = $parsed;
                        }
                    }

                    if (($answer['question_type'] ?? '') === 'file' && isset($documents[$questionId])) {
                        $decoded = $documents[$questionId];
                    }

                    return [
                        'question_id' => $questionId,
                        'question_label' => $answer['question_label'] ?? null,
                        'question_type' => $answer['question_type'] ?? null,
                        'answer' => $decoded,
                    ];
                })
                ->values()
                ->all();

            $payload['uploaded_documents'] = $documents;
            $payload['system_field_answers'] = $application->system_field_answers ?? [];
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $questions
     * @return list<array<string, mixed>>
     */
    private function buildCustomAnswersPayload(array $answers, $questions): array
    {
        return collect($answers)
            ->map(function (array $answer) use ($questions) {
                $questionId = (string) ($answer['question_id'] ?? '');
                $question = $questions->firstWhere('id', $questionId);

                $questionType = $question['type'] ?? ($answer['question_type'] ?? null);
                $answerValue = $answer['answer'] ?? null;

                if ($questionType === 'file') {
                    $storedAnswer = $this->sanitizeFileAnswerForStorage($answerValue);
                } else {
                    $storedAnswer = $this->normalizeAnswerText($answerValue);
                }

                return [
                    'question_id' => $questionId,
                    'question_label' => $question['label'] ?? ($answer['question_label'] ?? null),
                    'question_type' => $questionType,
                    'answer' => $storedAnswer,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{eligible: bool, message: string}
     */
    private function evaluateEligibility(User $user, ScheduleProgram $program): array
    {
        $letter = strtoupper((string) $program->program_letter);

        if ($letter === 'I') {
            return $this->evaluateSportsEligibility($user, $program);
        }

        if ($letter !== 'A') {
            return ['eligible' => true, 'message' => ''];
        }

        $profile = $this->resolveKkProfile($user, ['education', 'youth_classification', 'youth_age_group']);
        $education = $this->normalizeScholarshipEducation((string) ($profile['education'] ?? ''));

        if ($education === '' || ! in_array($education, self::SCHOLARSHIP_EDUCATION_LEVELS, true)) {
            return [
                'eligible' => false,
                'message' => 'Scholarship applications are only open to Senior High School and College students.',
            ];
        }

        $details = is_array($program->scholarship_details) ? $program->scholarship_details : [];
        $criteria = is_array($details['eligibility'] ?? null) ? $details['eligibility'] : [];
        $targetLevels = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            is_array($details['scholarship_target_levels'] ?? null)
                ? (array) $details['scholarship_target_levels']
                : (trim((string) ($details['scholarship_target_level'] ?? '')) !== ''
                    ? [(string) $details['scholarship_target_level']]
                    : [])
        )));

        $allowedClassifications = array_values(array_intersect(
            self::ELIGIBILITY_YOUTH_CLASSIFICATIONS,
            array_map(fn ($value) => trim((string) $value), (array) ($criteria['youth_classifications'] ?? []))
        ));

        $allowedAgeGroups = array_values(array_intersect(
            self::ELIGIBILITY_YOUTH_AGE_GROUPS,
            array_map(fn ($value) => trim((string) $value), (array) ($criteria['youth_age_groups'] ?? []))
        ));

        $allowedEducationLevels = array_values(array_intersect(
            self::SCHOLARSHIP_EDUCATION_LEVELS,
            array_map(fn ($value) => trim((string) $value), (array) ($criteria['education_levels'] ?? []))
        ));

        if ($allowedEducationLevels !== [] && ! in_array($education, $allowedEducationLevels, true)) {
            $message = 'Your educational background does not meet this scholarship program\'s eligibility requirements.';
            if (in_array('senior_high', $targetLevels, true) && in_array('college', $targetLevels, true)) {
                $message = 'This scholarship is open to In School Youth with High School Level or College Level educational background only.';
            } elseif (in_array('senior_high', $targetLevels, true)) {
                $message = 'This scholarship is open to In School Youth with High School Level educational background only.';
            } elseif (in_array('college', $targetLevels, true)) {
                $message = 'This scholarship is open to In School Youth with College Level educational background only.';
            }

            return [
                'eligible' => false,
                'message' => $message,
            ];
        }

        $userClassification = trim((string) ($profile['youth_classification'] ?? ''));
        if ($allowedClassifications !== [] && ! in_array($userClassification, $allowedClassifications, true)) {
            return [
                'eligible' => false,
                'message' => $targetLevels !== []
                    ? 'This scholarship is open to In School Youth only.'
                    : 'Your youth classification does not meet this scholarship program\'s eligibility requirements.',
            ];
        }

        $userAgeGroup = trim((string) ($profile['youth_age_group'] ?? ''));
        if ($allowedAgeGroups !== [] && ! in_array($userAgeGroup, $allowedAgeGroups, true)) {
            return [
                'eligible' => false,
                'message' => 'Your youth age group does not meet this scholarship program\'s eligibility requirements.',
            ];
        }

        return ['eligible' => true, 'message' => ''];
    }

    /**
     * @return array{eligible: bool, message: string, eligible_classifications?: list<array<string, mixed>>, matched_classification?: ?array<string, mixed>}
     */
    private function evaluateSportsEligibility(User $user, ScheduleProgram $program): array
    {
        $age = $this->resolveUserAge($user);

        if ($age === null) {
            return [
                'eligible' => false,
                'message' => 'Your age could not be verified from KK Profiling. Please complete your profile.',
            ];
        }

        if ($age < 15 || $age > 30) {
            return [
                'eligible' => false,
                'message' => 'Sports programs are open to Kabataan members aged 15–30 only.',
            ];
        }

        $sportsDetails = is_array($program->sports_details) ? $program->sports_details : [];
        $eligibleClassifications = $this->findEligibleSportsClassifications($age, $sportsDetails);

        if ($eligibleClassifications === []) {
            return [
                'eligible' => false,
                'message' => 'Your age does not match any open division for this sports program.',
                'eligible_classifications' => [],
            ];
        }

        return [
            'eligible' => true,
            'message' => '',
            'eligible_classifications' => $eligibleClassifications,
            'matched_classification' => $eligibleClassifications[0] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $sportsDetails
     * @return list<array<string, mixed>>
     */
    private function findEligibleSportsClassifications(int $age, array $sportsDetails): array
    {
        $classifications = (array) ($sportsDetails['age_classifications'] ?? []);
        $openAll = (bool) ($sportsDetails['open_all'] ?? false);

        return array_values(array_filter($classifications, function (array $classification) use ($age, $openAll) {
            $isOpen = $openAll || (bool) ($classification['is_open'] ?? false);
            if (! $isOpen) {
                return false;
            }

            $minAge = (int) ($classification['min_age'] ?? 0);
            $maxAge = (int) ($classification['max_age'] ?? 0);

            return $age >= $minAge && $age <= $maxAge;
        }));
    }

    private function resolveUserAge(User $user): ?int
    {
        $profile = $this->resolveKkProfile($user, ['age', 'birthday']);
        $ageValue = $profile['age'] ?? null;

        if ($ageValue !== null && $ageValue !== '' && is_numeric($ageValue)) {
            return (int) $ageValue;
        }

        $birthday = trim((string) ($profile['birthday'] ?? ''));
        if ($birthday === '') {
            return null;
        }

        try {
            return Carbon::parse($birthday)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     */
    private function validateSportsTeamCapacity(ScheduleProgram $program, array $answers): void
    {
        $sportsDetails = is_array($program->sports_details) ? $program->sports_details : [];
        $maxMembers = (int) ($sportsDetails['max_team_members'] ?? 12);
        if ($maxMembers < 1) {
            $maxMembers = 1;
        }
        if ($maxMembers > 12) {
            $maxMembers = 12;
        }

        $teamName = $this->extractTeamNameFromAnswers($answers, (array) ($program->custom_questions ?? []));
        if ($teamName === null || trim($teamName) === '') {
            return;
        }

        $normalizedTeamName = mb_strtolower(trim($teamName));
        $existingCount = ProgramApplication::query()
            ->where('program_id', $program->id)
            ->whereIn('status', [ProgramApplication::STATUS_PENDING, ProgramApplication::STATUS_APPROVED])
            ->get()
            ->filter(function (ProgramApplication $application) use ($normalizedTeamName) {
                $existingTeam = $this->extractTeamNameFromApplication($application);

                return $existingTeam !== null && mb_strtolower(trim($existingTeam)) === $normalizedTeamName;
            })
            ->count();

        if ($existingCount >= $maxMembers) {
            throw ValidationException::withMessages([
                'answers' => ["Team \"{$teamName}\" already has the maximum of {$maxMembers} members."],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @param  list<array<string, mixed>>  $questions
     */
    private function extractTeamNameFromAnswers(array $answers, array $questions): ?string
    {
        $teamQuestion = collect($questions)->first(function (array $question) {
            $fieldKey = (string) ($question['field_key'] ?? '');
            $label = mb_strtolower(trim((string) ($question['label'] ?? '')));

            return $fieldKey === 'team_name' || $label === 'team name';
        });

        if ($teamQuestion === null) {
            return null;
        }

        $questionId = (string) ($teamQuestion['id'] ?? '');
        $answer = collect($answers)->firstWhere('question_id', $questionId);

        return trim((string) ($answer['answer'] ?? ''));
    }

    private function extractTeamNameFromApplication(ProgramApplication $application): ?string
    {
        foreach ((array) ($application->custom_answers ?? []) as $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $fieldKey = (string) ($answer['field_key'] ?? '');
            $label = mb_strtolower(trim((string) ($answer['question_label'] ?? '')));
            if ($fieldKey === 'team_name' || $label === 'team name') {
                $value = trim((string) ($answer['answer'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function normalizeScholarshipEducation(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (strcasecmp($trimmed, 'High school level') === 0 || strcasecmp($trimmed, 'High School Level') === 0) {
            return 'High School Level';
        }

        if (strcasecmp($trimmed, 'College Level') === 0) {
            return 'College Level';
        }

        return $trimmed;
    }

    /**
     * @param  list<string>  $selectedFields
     * @return array<string, string>
     */
    private function resolveKkProfile(User $user, array $selectedFields): array
    {
        $registration = KabataanRegistration::query()
            ->with('barangay')
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->first();

        $formData = $registration?->form_data ?? [];

        $survey = $registration
            ? KkSurveyResponse::query()->where('kabataan_registration_id', $registration->id)->first()
            : null;

        if ($survey !== null) {
            $formData = array_merge($formData, array_filter([
                'last_name' => $survey->last_name,
                'first_name' => $survey->first_name,
                'middle_name' => $survey->middle_name,
                'suffix' => $survey->suffix,
                'birthday' => $survey->birthdate?->format('Y-m-d'),
                'date_of_birth' => $survey->birthdate?->format('Y-m-d'),
                'age' => $survey->age,
                'sex' => $survey->sex_assigned_at_birth,
                'gender' => $survey->sex_assigned_at_birth,
                'civil_status' => $survey->civil_status,
                'contact_number' => $survey->contact_number,
                'email' => $survey->email,
                'region' => $survey->region,
                'province' => $survey->province,
                'city' => $survey->municipality,
                'city_municipality' => $survey->municipality,
                'barangay' => $survey->barangay,
                'purok_zone' => $survey->purok_zone,
                'youth_classification' => $survey->youth_classification,
                'youth_age_group' => $survey->youth_age_group,
                'education' => $survey->educational_background,
                'work_status' => $survey->work_status,
                'sk_voter' => $survey->registered_sk_voter ? 'Yes' : 'No',
                'sk_voted' => $survey->voted_last_sk ? 'Yes' : 'No',
            ], fn ($value) => $value !== null && trim((string) $value) !== ''));
        }

        $fullName = trim(implode(' ', array_filter([
            $registration?->first_name ?? ($formData['first_name'] ?? ''),
            $registration?->middle_name ?? ($formData['middle_name'] ?? ''),
            $registration?->last_name ?? ($formData['last_name'] ?? ''),
            $this->resolveSuffixFromRegistration($registration, $formData),
        ], fn ($part) => $this->isMeaningfulNamePart($part))));

        $mapped = [
            'last_name' => $registration?->last_name ?? ($formData['last_name'] ?? ''),
            'first_name' => $registration?->first_name ?? ($formData['first_name'] ?? ''),
            'middle_name' => $registration?->middle_name ?? ($formData['middle_name'] ?? ''),
            'suffix' => $this->resolveSuffixForKkProfile($registration, $formData),
            'full_name' => $fullName !== '' ? $fullName : ($user->name ?? ''),
            'birthday' => $formData['birthday'] ?? ($formData['date_of_birth'] ?? ''),
            'age' => (string) ($formData['age'] ?? ''),
            'sex' => $formData['sex'] ?? ($formData['gender'] ?? ''),
            'civil_status' => $this->stringifyProfileValue($formData['civil_status'] ?? ''),
            'contact_number' => $registration?->contact_number ?? ($formData['contact_number'] ?? ''),
            'email' => $registration?->email ?? ($user->email ?? ''),
            'region' => $formData['region'] ?? '',
            'province' => $formData['province'] ?? '',
            'city' => $formData['city'] ?? ($formData['city_municipality'] ?? ''),
            'city_municipality' => $formData['city_municipality'] ?? ($formData['city'] ?? ''),
            'barangay' => $registration?->barangay?->name ?? ($formData['barangay'] ?? ''),
            'purok_zone' => $formData['purok_zone'] ?? ($formData['purok'] ?? ''),
            'youth_classification' => $this->stringifyProfileValue($formData['youth_classification'] ?? ''),
            'youth_age_group' => $this->stringifyProfileValue($formData['youth_age_group'] ?? ''),
            'education' => $this->stringifyProfileValue($formData['education'] ?? ''),
            'current_school' => $formData['current_school'] ?? ($formData['school'] ?? ''),
            'course_strand' => $formData['course_strand'] ?? ($formData['course'] ?? ''),
            'work_status' => $formData['work_status'] ?? '',
            'sk_voter' => $formData['sk_voter'] ?? '',
            'sk_voted' => $formData['sk_voted'] ?? '',
            'home_address' => $formData['home_address'] ?? ($formData['address'] ?? ''),
            'year_level' => $formData['year_level'] ?? '',
        ];

        if ($selectedFields === []) {
            return array_filter($mapped, fn ($value) => trim((string) $value) !== '');
        }

        $result = [];
        foreach ($selectedFields as $field) {
            $key = (string) $field;
            $value = $mapped[$key] ?? '';
            if (is_array($value)) {
                $parts = array_filter(
                    array_map(fn ($part) => trim((string) $part), $value),
                    fn ($part) => $part !== '',
                );
                $value = implode(', ', $parts);
            }
            $result[$key] = trim((string) $value);
        }

        return $result;
    }

    private function stringifyProfileValue(mixed $value): string
    {
        if (is_array($value)) {
            $parts = array_filter(
                array_map(fn ($part) => trim((string) $part), $value),
                fn ($part) => $part !== '',
            );

            return implode(', ', $parts);
        }

        return trim((string) ($value ?? ''));
    }

    /**
     * Suffix value for KK profile display (includes "None" when applicable).
     *
     * @param  array<string, mixed>  $formData
     */
    private function resolveSuffixForKkProfile(?KabataanRegistration $registration, array $formData): string
    {
        $suffix = trim((string) ($registration?->suffix ?? ($formData['suffix'] ?? '')));

        if ($suffix === 'Others') {
            $custom = trim((string) ($formData['custom_suffix'] ?? ''));

            return $custom !== '' ? $custom : 'Others';
        }

        return $suffix;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  list<array<string, mixed>>  $answers
     */
    private function validateAnswers(array $questions, array $answers): void
    {
        $answersById = collect($answers)->keyBy('question_id');

        foreach ($questions as $question) {
            if (! ($question['required'] ?? false)) {
                continue;
            }

            $questionId = (string) ($question['id'] ?? '');
            $answer = $answersById->get($questionId);
            $value = $answer['answer'] ?? null;

            if (($question['type'] ?? '') === 'file') {
                $hasFile = is_array($value) && ! empty($value['path']);
                if (! $hasFile) {
                    $label = $question['label'] ?? 'A required document';
                    throw ValidationException::withMessages([
                        "answers.{$questionId}" => ["{$label} is required."],
                    ]);
                }

                continue;
            }

            if ($this->isEmptyAnswer($value)) {
                $label = $question['label'] ?? 'A required question';
                throw ValidationException::withMessages([
                    "answers.{$questionId}" => ["{$label} is required."],
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, array<string, mixed>>
     */
    private function resolveUploadedDocuments(
        User $user,
        int $scheduleProgramId,
        array $questions,
        ?ProgramApplication $application,
    ): array {
        if ($application !== null) {
            return $this->documentService->listApplicationDocuments($application);
        }

        $fileQuestionIds = collect($questions)
            ->filter(fn (array $question) => ($question['type'] ?? '') === 'file')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $this->documentService->listDraftDocuments($user, $scheduleProgramId, $fileQuestionIds);
    }

    private function isEmptyAnswer(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return trim((string) $value) === '';
    }

    private function normalizeAnswerText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizeFileAnswerForStorage(mixed $answerValue): ?array
    {
        $meta = is_array($answerValue) ? $answerValue : null;

        if ($meta === null && is_string($answerValue)) {
            $decoded = json_decode($answerValue, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        if ($meta === null || empty($meta['path'])) {
            return null;
        }

        return [
            'question_id' => $meta['question_id'] ?? null,
            'path' => str_replace('\\', '/', (string) $meta['path']),
            'original_name' => $meta['original_name'] ?? null,
            'size' => isset($meta['size']) ? (int) $meta['size'] : null,
            'mime' => $meta['mime'] ?? 'application/pdf',
            'uploaded_at' => $meta['uploaded_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildApplicationProfileData(User $user, KabataanRegistration $registration): array
    {
        $formData = $registration->form_data ?? [];

        $survey = KkSurveyResponse::query()
            ->where('kabataan_registration_id', $registration->id)
            ->first();

        if ($survey !== null) {
            $formData = array_merge($formData, array_filter([
                'last_name' => $survey->last_name,
                'first_name' => $survey->first_name,
                'middle_name' => $survey->middle_name,
                'suffix' => $survey->suffix,
                'birthday' => $survey->birthdate?->format('Y-m-d'),
                'date_of_birth' => $survey->birthdate?->format('Y-m-d'),
                'age' => $survey->age,
                'sex' => $survey->sex_assigned_at_birth,
                'gender' => $survey->sex_assigned_at_birth,
                'civil_status' => $survey->civil_status,
                'contact_number' => $survey->contact_number,
                'email' => $survey->email,
                'region' => $survey->region,
                'province' => $survey->province,
                'city' => $survey->municipality,
                'city_municipality' => $survey->municipality,
                'barangay' => $survey->barangay,
                'purok_zone' => $survey->purok_zone,
                'youth_classification' => $survey->youth_classification,
                'youth_age_group' => $survey->youth_age_group,
                'education' => $survey->educational_background,
                'work_status' => $survey->work_status,
                'sk_voter' => $survey->registered_sk_voter ? 'Yes' : 'No',
                'sk_voted' => $survey->voted_last_sk ? 'Yes' : 'No',
            ], fn ($value) => $value !== null && trim((string) $value) !== ''));
        }

        $birthdate = $this->parseBirthdate($formData['birthday'] ?? ($formData['date_of_birth'] ?? null));

        if ($birthdate === null) {
            throw ValidationException::withMessages([
                'birthdate' => ['Birthdate is required from your KK Profile before applying.'],
            ]);
        }

        return [
            'first_name' => $this->nullableString($registration->first_name ?? ($formData['first_name'] ?? null)),
            'middle_name' => $this->nullableString($registration->middle_name ?? ($formData['middle_name'] ?? null)),
            'last_name' => $this->nullableString($registration->last_name ?? ($formData['last_name'] ?? null)),
            'suffix' => $this->resolveSuffixForStorage($registration, $formData),
            'birthdate' => $birthdate,
            'age' => isset($formData['age']) ? (int) $formData['age'] : null,
            'sex' => $this->nullableString($formData['sex'] ?? ($formData['gender'] ?? null)),
            'civil_status' => $this->nullableString($formData['civil_status'] ?? null),
            'purok' => $this->nullableString($formData['purok_zone'] ?? ($formData['purok'] ?? null)),
            'barangay' => $this->nullableString($registration->barangay?->name ?? ($formData['barangay'] ?? null)),
            'email' => $this->nullableString($registration->email ?? $user->email),
            'contact_number' => $this->nullableString($registration->contact_number ?? ($formData['contact_number'] ?? null)),
            'school_name' => $this->nullableString($formData['current_school'] ?? ($formData['school'] ?? null)),
            'grade_level' => $this->nullableString($formData['year_level'] ?? null),
            'course' => $this->nullableString($formData['course_strand'] ?? ($formData['course'] ?? null)),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '' || in_array(strtolower($trimmed), ['null', 'n/a'], true)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function resolveSuffixFromRegistration(?KabataanRegistration $registration, array $formData): string
    {
        $suffix = trim((string) ($registration?->suffix ?? ($formData['suffix'] ?? '')));

        if ($suffix === 'Others') {
            $suffix = trim((string) ($formData['custom_suffix'] ?? ''));
        }

        return $this->isMeaningfulNamePart($suffix) ? $suffix : '';
    }

    private function isMeaningfulNamePart(mixed $value): bool
    {
        $part = trim((string) $value);
        if ($part === '') {
            return false;
        }

        return ! in_array(strtolower($part), ['none', 'n/a', 'na', 'null', '-', '—'], true);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function resolveSuffixForStorage(KabataanRegistration $registration, array $formData): ?string
    {
        $suffix = $this->resolveSuffixFromRegistration($registration, $formData);

        return $suffix === '' ? null : $suffix;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function resolveSuffixForDisplay(?string $storedSuffix, ?KabataanRegistration $registration, array $formData): string
    {
        $suffix = trim((string) ($storedSuffix ?? ''));
        if ($suffix === '') {
            $suffix = $this->resolveSuffixFromRegistration($registration, $formData);
        }

        if ($suffix === '') {
            return '—';
        }

        return $suffix;
    }

    private function countActiveApplications(int $scheduleProgramId): int
    {
        return ProgramApplication::query()
            ->where('program_id', $scheduleProgramId)
            ->whereNot('status', ProgramApplication::STATUS_CANCELLED)
            ->count();
    }

    private function calculateAvailableSlots(ScheduleProgram $program): ?int
    {
        if ($program->participation_quantity === null) {
            return null;
        }

        $used = $this->countActiveApplications($program->id);

        return max(0, $program->participation_quantity - $used);
    }

    /**
     * @param  array<string, mixed>  $sportsDetails
     */
    private function resolveSportLabel(array $sportsDetails): ?string
    {
        $sportKey = strtolower(trim((string) ($sportsDetails['sport_key'] ?? '')));
        $label = trim((string) ($sportsDetails['sport_label'] ?? ''));
        $otherName = trim((string) ($sportsDetails['other_sport_name'] ?? ''));

        if ($sportKey === 'other') {
            if ($otherName !== '') {
                return $otherName;
            }

            if ($label !== '' && strtolower($label) !== 'other') {
                return $label;
            }

            return $label !== '' ? $label : null;
        }

        if ($label !== '' && strtolower($label) !== 'other') {
            return $label;
        }

        return match ($sportKey) {
            'basketball' => 'Basketball',
            'volleyball' => 'Volleyball',
            default => $label !== '' ? $label : null,
        };
    }

    private function formatProgramPeriod(?ScheduleProgram $program): string
    {
        if ($program === null) {
            return '—';
        }

        return $this->formatDate($program->start_date).' - '.$this->formatDate($program->end_date);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function formatApplicationPersonalInfo(ProgramApplication $application, User $user): array
    {
        $registration = KabataanRegistration::query()
            ->with('barangay')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $formData = $registration?->form_data ?? [];
        $selectedFields = $application->scheduleProgram?->kk_profiling_fields ?? [];
        $displayDate = $application->updated_at && $application->created_at
            && $application->updated_at->gt($application->created_at)
            ? $application->updated_at
            : $application->created_at;

        $mapped = [
            'last_name' => (string) ($application->last_name ?? ''),
            'first_name' => (string) ($application->first_name ?? ''),
            'middle_name' => (string) ($application->middle_name ?? ''),
            'suffix' => $this->resolveSuffixForDisplay($application->suffix, $registration, $formData),
            'full_name' => trim(implode(' ', array_filter([
                $application->first_name,
                $application->middle_name,
                $application->last_name,
                $this->resolveSuffixForDisplay($application->suffix, $registration, $formData),
            ], fn ($part) => $part !== null && trim((string) $part) !== '' && trim((string) $part) !== '—'))),
            'birthday' => $application->birthdate?->format('F j, Y') ?? ($formData['birthday'] ?? ''),
            'age' => $application->age !== null ? (string) $application->age : (string) ($formData['age'] ?? ''),
            'sex' => (string) ($application->sex ?? ($formData['sex'] ?? ($formData['gender'] ?? ''))),
            'civil_status' => (string) ($application->civil_status ?? ($formData['civil_status'] ?? '')),
            'contact_number' => (string) ($application->contact_number ?? ($formData['contact_number'] ?? '')),
            'email' => (string) ($application->email ?? ($registration?->email ?? $user->email ?? '')),
            'region' => (string) ($formData['region'] ?? ''),
            'province' => (string) ($formData['province'] ?? ''),
            'city' => (string) ($formData['city'] ?? ($formData['city_municipality'] ?? '')),
            'city_municipality' => (string) ($formData['city_municipality'] ?? ($formData['city'] ?? '')),
            'barangay' => (string) ($application->barangay ?? ($registration?->barangay?->name ?? ($formData['barangay'] ?? ''))),
            'purok_zone' => (string) ($application->purok ?? ($formData['purok_zone'] ?? ($formData['purok'] ?? ''))),
            'youth_classification' => (string) ($formData['youth_classification'] ?? ''),
            'youth_age_group' => (string) ($formData['youth_age_group'] ?? ''),
            'education' => (string) ($formData['education'] ?? ''),
            'current_school' => (string) ($application->school_name ?? ($formData['current_school'] ?? ($formData['school'] ?? ''))),
            'course_strand' => (string) ($application->course ?? ($formData['course_strand'] ?? ($formData['course'] ?? ''))),
            'work_status' => (string) ($formData['work_status'] ?? ''),
            'sk_voter' => (string) ($formData['sk_voter'] ?? ''),
            'sk_voted' => (string) ($formData['sk_voted'] ?? ''),
            'home_address' => (string) ($formData['home_address'] ?? ($formData['address'] ?? '')),
            'year_level' => (string) ($application->grade_level ?? ($formData['year_level'] ?? '')),
        ];

        $items = [
            ['label' => 'Program Name', 'value' => (string) ($application->scheduleProgram?->program_name ?? 'Program')],
            ['label' => 'Application Period', 'value' => $this->formatProgramPeriod($application->scheduleProgram)],
            ['label' => 'Application Date', 'value' => $displayDate?->format('F j, Y g:i A') ?? '—'],
            ['label' => 'Status', 'value' => ucfirst((string) $application->status)],
        ];

        if ($application->scheduleProgram?->committee) {
            $items[] = ['label' => 'Committee', 'value' => (string) $application->scheduleProgram->committee];
        }

        $fieldsToRender = $selectedFields !== []
            ? $selectedFields
            : array_keys(array_filter($mapped, fn ($value) => trim((string) $value) !== '' && trim((string) $value) !== '—'));

        foreach ($fieldsToRender as $field) {
            $key = (string) $field;
            $value = trim((string) ($mapped[$key] ?? ''));
            $alwaysShow = in_array($key, ['first_name', 'middle_name', 'last_name', 'suffix'], true);

            if (($value === '' || $value === '—') && ! $alwaysShow) {
                continue;
            }

            if ($value === '') {
                $value = '—';
            }

            $items[] = [
                'label' => self::KK_FIELD_LABELS[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'value' => $value,
            ];
        }

        return $items;
    }

    private function parseBirthdate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDate(?Carbon $date): string
    {
        return $date?->format('F j, Y') ?? '—';
    }

    /**
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $systemFields
     * @return array<string, mixed>
     */
    private function mergeSystemFieldsIntoProfile(array $profileData, array $systemFields): array
    {
        $motherName = trim(implode(' ', array_filter([
            $systemFields['mother_first_name'] ?? '',
            $systemFields['mother_last_name'] ?? '',
        ], fn ($part) => trim((string) $part) !== '')));

        $fatherName = trim(implode(' ', array_filter([
            $systemFields['father_first_name'] ?? '',
            $systemFields['father_last_name'] ?? '',
        ], fn ($part) => trim((string) $part) !== '')));

        $guardianName = trim(implode(' ', array_filter([
            $systemFields['guardian_first_name'] ?? '',
            $systemFields['guardian_last_name'] ?? '',
        ], fn ($part) => trim((string) $part) !== '')));

        $parentName = $motherName !== '' ? $motherName : ($fatherName !== '' ? $fatherName : $guardianName);
        $parentOccupation = $this->resolveStoredOccupation($systemFields, 'mother')
            ?? $this->resolveStoredOccupation($systemFields, 'father')
            ?? $this->resolveStoredOccupation($systemFields, 'guardian');
        $incomeValue = trim((string) ($systemFields['annual_family_gross_income'] ?? ''));

        $profileData['parent_guardian_name'] = $this->nullableString($parentName);
        $profileData['parent_occupation'] = $this->nullableString($parentOccupation);
        $profileData['parent_income'] = $incomeValue !== '' ? $incomeValue : null;
        $profileData['school_name'] = $this->nullableString($systemFields['school_name'] ?? $profileData['school_name'] ?? null);
        $profileData['grade_level'] = $this->nullableString($systemFields['year_level'] ?? $profileData['grade_level'] ?? null);
        $profileData['course'] = $this->nullableString($systemFields['strand'] ?? $profileData['course'] ?? null);

        return $profileData;
    }

    /**
     * @param  array<string, mixed>  $systemFields
     */
    private function resolveStoredOccupation(array $systemFields, string $prefix): ?string
    {
        $occupation = trim((string) ($systemFields["{$prefix}_occupation"] ?? ''));
        $other = trim((string) ($systemFields["{$prefix}_occupation_other"] ?? ''));

        if ($occupation === 'Other Occupation' && $other !== '') {
            return mb_strtoupper($other);
        }

        if ($occupation !== '') {
            return $occupation;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function kkFieldLabels(): array
    {
        return self::KK_FIELD_LABELS;
    }

    /**
     * @return list<array{en: string, tl: string}>
     */
    private function resolveQuickGuidelinesForProgram(ScheduleProgram $program): array
    {
        if (! $program->relationLoaded('quickGuidelineSteps')) {
            $program->load('quickGuidelineSteps');
        }

        $steps = $program->quickGuidelineSteps
            ->map(fn (ScholarshipQuickGuidelineStep $step) => [
                'en' => (string) $step->content_en,
                'tl' => (string) $step->content_tl,
            ])
            ->values()
            ->all();

        if ($steps !== []) {
            return $steps;
        }

        $legacy = is_array($program->scholarship_details)
            ? ($program->scholarship_details['quick_guidelines'] ?? null)
            : null;

        if (! is_array($legacy)) {
            return [];
        }

        $sanitized = [];
        foreach ($legacy as $step) {
            if (! is_array($step)) {
                continue;
            }

            $en = trim((string) ($step['en'] ?? ''));
            $tl = trim((string) ($step['tl'] ?? ''));
            if ($en === '' && $tl === '') {
                continue;
            }

            $sanitized[] = [
                'en' => $en !== '' ? $en : $tl,
                'tl' => $tl !== '' ? $tl : $en,
            ];

            if (count($sanitized) >= 10) {
                break;
            }
        }

        return $sanitized;
    }
}
