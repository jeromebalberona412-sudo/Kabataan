<?php

namespace App\Modules\Programs\Services;

use App\Models\KabataanRegistration;
use App\Models\ProgramSurvey;
use App\Models\ProgramSurveyQuestion;
use App\Models\ProgramSurveyResponse;
use App\Models\ProgramSurveyResponseAnswer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KabataanProgramSurveyService
{
    /**
     * @param  list<int>  $programIds
     * @return array<int, array<string, mixed>>
     */
    public function summarizeOpenSurveysForPrograms(User $user, array $programIds): array
    {
        if ($programIds === []) {
            return [];
        }

        try {
            $surveys = $this->openSurveyQuery($user)
                ->with('abyipProgram')
                ->whereIn('abyip_program_id', $programIds)
                ->get();

            $map = [];
            foreach ($surveys as $survey) {
                $map[(int) $survey->abyip_program_id] = $this->formatSurveySummary($survey, $user);
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Latest survey per program (any status) — used to list programs after SK Officials creates a survey.
     *
     * @param  list<int>  $programIds
     * @return array<int, array<string, mixed>>
     */
    public function summarizeLatestSurveysForPrograms(User $user, array $programIds): array
    {
        if ($programIds === []) {
            return [];
        }

        try {
            $surveys = $this->scopedSurveyQuery($user)
                ->with('abyipProgram')
                ->whereIn('abyip_program_id', $programIds)
                ->orderByDesc('open_date')
                ->orderByDesc('id')
                ->get();

            $map = [];
            foreach ($surveys as $survey) {
                $programId = (int) $survey->abyip_program_id;
                if (! isset($map[$programId])) {
                    $map[$programId] = $this->formatSurveySummary($survey, $user);
                }
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUserResponseDetails(User $user): array
    {
        try {
            $registration = KabataanRegistration::query()
                ->where('user_id', $user->id)
                ->latest()
                ->first();

            if ($registration === null) {
                return [];
            }

            return ProgramSurveyResponse::query()
                ->with(['survey.abyipProgram', 'survey.questions', 'answers.question'])
                ->where('registration_id', $registration->id)
                ->whereHas('survey', function ($query) use ($user) {
                    $this->applyUserScope($query, $user);
                })
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (ProgramSurveyResponse $response) => $this->formatResponseDetail($response))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function summarizeOpenSurveyForProgram(User $user, int $abyipProgramId): ?array
    {
        try {
            $survey = $this->openSurveyQuery($user)
                ->with('abyipProgram')
                ->where('abyip_program_id', $abyipProgramId)
                ->first();

            if ($survey === null) {
                return null;
            }

            return $this->formatSurveySummary($survey, $user);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSurveyForUser(User $user, int $surveyId): ?array
    {
        $survey = $this->scopedSurveyQuery($user)
            ->with(['abyipProgram', 'questions'])
            ->find($surveyId);

        if ($survey === null) {
            return null;
        }

        return $this->formatSurveyDetail($survey, $user);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOpenSurveyByProgram(User $user, int $abyipProgramId): ?array
    {
        $survey = $this->openSurveyQuery($user)
            ->where('abyip_program_id', $abyipProgramId)
            ->with(['abyipProgram', 'questions'])
            ->first();

        if ($survey === null) {
            return null;
        }

        return $this->formatSurveyDetail($survey, $user);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatestSurveyByProgram(User $user, int $abyipProgramId): ?array
    {
        try {
            $survey = $this->scopedSurveyQuery($user)
                ->where('abyip_program_id', $abyipProgramId)
                ->with(['abyipProgram', 'questions'])
                ->orderByDesc('open_date')
                ->orderByDesc('id')
                ->first();

            if ($survey === null) {
                return null;
            }

            return $this->formatSurveyDetail($survey, $user);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUserResponses(User $user, ?int $abyipProgramId = null): array
    {
        $registration = $this->requireRegistration($user);

        return ProgramSurveyResponse::query()
            ->with(['survey.abyipProgram', 'answers.question'])
            ->where('registration_id', $registration->id)
            ->when($abyipProgramId !== null, function ($query) use ($abyipProgramId) {
                $query->whereHas('survey', fn ($inner) => $inner->where('abyip_program_id', $abyipProgramId));
            })
            ->whereHas('survey', function ($query) use ($user) {
                $this->applyUserScope($query, $user);
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProgramSurveyResponse $response) => $this->formatResponseSummary($response))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserResponse(User $user, int $responseId): array
    {
        $registration = $this->requireRegistration($user);

        $response = ProgramSurveyResponse::query()
            ->with(['survey.abyipProgram', 'survey.questions', 'answers.question'])
            ->where('registration_id', $registration->id)
            ->whereHas('survey', function ($query) use ($user) {
                $this->applyUserScope($query, $user);
            })
            ->find($responseId);

        if ($response === null) {
            throw ValidationException::withMessages([
                'response_id' => ['Survey response not found.'],
            ]);
        }

        return $this->formatResponseDetail($response);
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @return array<string, mixed>
     */
    public function submitResponse(User $user, int $surveyId, array $answers): array
    {
        $registration = $this->requireRegistration($user);

        $survey = $this->openSurveyQuery($user)
            ->with('questions')
            ->find($surveyId);

        if ($survey === null) {
            throw ValidationException::withMessages([
                'survey_id' => ['Survey not found or not open for your barangay.'],
            ]);
        }

        if (! $this->isSurveyCurrentlyOpen($survey)) {
            throw ValidationException::withMessages([
                'survey_id' => ['This survey is not open for responses right now.'],
            ]);
        }

        $existing = ProgramSurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->where('registration_id', $registration->id)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'survey_id' => ['You have already submitted a response for this survey.'],
            ]);
        }

        $validatedAnswers = $this->validateAnswers($survey->questions->all(), $answers);

        return DB::transaction(function () use ($survey, $registration, $validatedAnswers) {
            $response = ProgramSurveyResponse::create([
                'survey_id' => $survey->id,
                'registration_id' => $registration->id,
                'submitted_at' => now(),
            ]);

            foreach ($validatedAnswers as $answer) {
                ProgramSurveyResponseAnswer::create([
                    'response_id' => $response->id,
                    'question_id' => $answer['question_id'],
                    'answer' => $answer['answer'],
                ]);
            }

            $response->load(['survey.abyipProgram', 'answers.question']);

            try {
                $program = $response->survey?->abyipProgram;
                (new SkOfficialsNotificationDispatcher())->notifySurveyResponse(
                    (int) $survey->barangay_id,
                    (string) ($registration->full_name ?? 'A Kabataan member'),
                    (string) ($program?->program_name ?? 'a program survey'),
                    $program?->program_letter,
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return $this->formatResponseDetail($response);
        });
    }

    private function openSurveyQuery(User $user)
    {
        $today = Carbon::today()->toDateString();

        return $this->scopedSurveyQuery($user)
            ->whereDate('close_date', '>=', $today)
            ->where('status', 'open');
    }

    private function scopedSurveyQuery(User $user)
    {
        $query = ProgramSurvey::query();

        $this->applyUserScope($query, $user);

        return $query;
    }

    private function applyUserScope($query, User $user): void
    {
        $tenantId = $this->resolveUserTenantId($user);
        $barangayId = $this->resolveUserBarangayId($user);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($barangayId !== null) {
            $query->where('barangay_id', $barangayId);
        }
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

    private function isSurveyCurrentlyOpen(ProgramSurvey $survey): bool
    {
        $today = Carbon::today();
        $status = strtolower(trim((string) $survey->status));

        if ($today->gt($survey->close_date)) {
            return false;
        }

        // SK Officials explicitly set status to Open — allow responses until close date.
        if ($status === 'open') {
            return true;
        }

        if ($status === 'scheduled') {
            return $today->gte($survey->open_date);
        }

        return false;
    }

    private function requireRegistration(User $user): KabataanRegistration
    {
        $registration = KabataanRegistration::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if ($registration === null) {
            throw ValidationException::withMessages([
                'registration' => ['KK Profiling registration is required before answering surveys.'],
            ]);
        }

        return $registration;
    }

    private function userHasResponded(User $user, ProgramSurvey $survey): bool
    {
        $registrationId = KabataanRegistration::query()
            ->where('user_id', $user->id)
            ->latest()
            ->value('id');

        if ($registrationId === null) {
            return false;
        }

        return ProgramSurveyResponse::query()
            ->where('survey_id', $survey->id)
            ->where('registration_id', $registrationId)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSurveySummary(ProgramSurvey $survey, User $user): array
    {
        $isOpen = $this->isSurveyCurrentlyOpen($survey);
        $hasResponded = $this->userHasResponded($user, $survey);

        return [
            'id' => $survey->id,
            'abyip_program_id' => $survey->abyip_program_id,
            'program_name' => trim((string) ($survey->abyipProgram?->program_name ?? '')),
            'announcement' => $survey->announcement,
            'open_date' => $survey->open_date->format('Y-m-d'),
            'close_date' => $survey->close_date->format('Y-m-d'),
            'open_date_display' => $this->formatDate($survey->open_date),
            'close_date_display' => $this->formatDate($survey->close_date),
            'status' => $survey->status,
            'is_open' => $isOpen,
            'has_responded' => $hasResponded,
            'can_respond' => $isOpen && ! $hasResponded,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSurveyDetail(ProgramSurvey $survey, User $user): array
    {
        $summary = $this->formatSurveySummary($survey, $user);

        $summary['instructions'] = $survey->instructions;
        $summary['questions'] = $survey->questions
            ->map(fn (ProgramSurveyQuestion $question) => [
                'id' => $question->id,
                'label' => $question->question_label,
                'type' => $question->input_type,
                'required' => $question->is_required,
                'options' => $question->options ?? [],
            ])
            ->values()
            ->all();

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatResponseSummary(ProgramSurveyResponse $response): array
    {
        $survey = $response->survey;

        return [
            'id' => $response->id,
            'survey_id' => $response->survey_id,
            'abyip_program_id' => $survey?->abyip_program_id,
            'program_name' => trim((string) ($survey?->abyipProgram?->program_name ?? 'Program Survey')),
            'submitted_at' => $response->submitted_at?->format('M j, Y g:i A'),
            'submitted_at_iso' => $response->submitted_at?->toIso8601String(),
            'survey_period' => $survey
                ? $this->formatDate($survey->open_date).' - '.$this->formatDate($survey->close_date)
                : '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatResponseDetail(ProgramSurveyResponse $response): array
    {
        $summary = $this->formatResponseSummary($response);
        $survey = $response->survey;

        $answersByQuestion = [];
        foreach ($response->answers as $answer) {
            $answersByQuestion[(int) $answer->question_id] = $answer->answer;
        }

        $summary['answers'] = collect($survey?->questions ?? [])
            ->map(function (ProgramSurveyQuestion $question) use ($answersByQuestion) {
                $raw = $answersByQuestion[(int) $question->id] ?? null;
                $decoded = $this->decodeStoredAnswer($raw);

                return [
                    'question_id' => $question->id,
                    'question_label' => $question->question_label,
                    'question_type' => $question->input_type,
                    'answer' => $decoded,
                ];
            })
            ->values()
            ->all();

        return $summary;
    }

    /**
     * @param  list<ProgramSurveyQuestion>  $questions
     * @param  list<array<string, mixed>>  $answers
     * @return list<array{question_id: int, answer: string|null}>
     */
    private function validateAnswers(array $questions, array $answers): array
    {
        if ($questions === []) {
            throw ValidationException::withMessages([
                'answers' => ['This survey has no questions yet.'],
            ]);
        }

        $answersById = collect($answers)->keyBy(fn ($answer) => (int) ($answer['question_id'] ?? 0));
        $validated = [];

        foreach ($questions as $question) {
            $answerRow = $answersById->get((int) $question->id);
            $value = $answerRow['answer'] ?? null;

            if ($question->is_required && $this->isEmptyAnswer($value)) {
                throw ValidationException::withMessages([
                    "answers.{$question->id}" => ["{$question->question_label} is required."],
                ]);
            }

            if ($this->isEmptyAnswer($value)) {
                continue;
            }

            $validated[] = [
                'question_id' => (int) $question->id,
                'answer' => $this->normalizeAnswerForStorage($question, $value),
            ];
        }

        return $validated;
    }

    private function isEmptyAnswer(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        return trim((string) $value) === '';
    }

    private function normalizeAnswerForStorage(ProgramSurveyQuestion $question, mixed $value): ?string
    {
        if ($question->input_type === 'checkbox' && is_array($value)) {
            return json_encode(array_values($value));
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function decodeStoredAnswer(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return $value;
    }

    private function formatDate(?Carbon $date): string
    {
        return $date?->format('F j, Y') ?? '—';
    }
}
