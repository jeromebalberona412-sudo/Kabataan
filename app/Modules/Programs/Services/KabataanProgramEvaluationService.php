<?php

namespace App\Modules\Programs\Services;

use App\Models\KabataanRegistration;
use App\Models\ProgramEvaluation;
use App\Models\ProgramEvaluationResponse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KabataanProgramEvaluationService
{
    /**
     * @param  list<int>  $programIds
     * @return array<int, array<string, mixed>>
     */
    public function summarizeOpenEvaluationsForPrograms(User $user, array $programIds): array
    {
        if ($programIds === []) {
            return [];
        }

        try {
            $evaluations = $this->openEvaluationQuery($user)
                ->with('abyipProgram')
                ->whereIn('abyip_program_id', $programIds)
                ->get();

            $map = [];
            foreach ($evaluations as $evaluation) {
                $map[(int) $evaluation->abyip_program_id] = $this->formatEvaluationSummary($evaluation, $user);
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingEvaluationsForUser(User $user): array
    {
        try {
            return $this->openEvaluationQuery($user)
                ->with('abyipProgram')
                ->orderByDesc('created_at')
                ->get()
                ->filter(fn (ProgramEvaluation $evaluation) => $this->isEvaluationCurrentlyOpen($evaluation)
                    && ! $this->userHasResponded($user, $evaluation))
                ->map(fn (ProgramEvaluation $evaluation) => $this->formatEvaluationSummary($evaluation, $user, true))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOpenEvaluationByProgram(User $user, int $abyipProgramId): ?array
    {
        try {
            $evaluation = $this->openEvaluationQuery($user)
                ->with('abyipProgram')
                ->where('abyip_program_id', $abyipProgramId)
                ->first();

            return $evaluation ? $this->formatEvaluationSummary($evaluation, $user, true) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEvaluationForUser(User $user, int $evaluationId): ?array
    {
        try {
            $evaluation = $this->scopedEvaluationQuery($user)
                ->with('abyipProgram')
                ->whereKey($evaluationId)
                ->first();

            if ($evaluation === null) {
                return null;
            }

            return $this->formatEvaluationSummary($evaluation, $user, true);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @return array<string, mixed>
     */
    public function submitResponse(User $user, int $evaluationId, array $answers): array
    {
        $registration = $this->requireRegistration($user);

        $evaluation = $this->scopedEvaluationQuery($user)
            ->with('abyipProgram')
            ->whereKey($evaluationId)
            ->first();

        if ($evaluation === null) {
            throw ValidationException::withMessages([
                'evaluation_id' => ['Evaluation form not found for your barangay.'],
            ]);
        }

        if (! $this->isEvaluationCurrentlyOpen($evaluation)) {
            throw ValidationException::withMessages([
                'evaluation_id' => ['This evaluation is no longer open for responses.'],
            ]);
        }

        if ($this->userHasResponded($user, $evaluation)) {
            throw ValidationException::withMessages([
                'evaluation_id' => ['You have already submitted your evaluation for this program.'],
            ]);
        }

        $validatedAnswers = $this->validateAnswers(
            is_array($evaluation->custom_questions) ? $evaluation->custom_questions : [],
            $answers,
        );

        return DB::transaction(function () use ($evaluation, $registration, $validatedAnswers) {
            $response = ProgramEvaluationResponse::create([
                'evaluation_id' => $evaluation->id,
                'registration_id' => $registration->id,
                'answers' => $validatedAnswers,
                'submitted_at' => now(),
            ]);

            return [
                'id' => $response->id,
                'evaluation_id' => $evaluation->id,
                'submitted_at' => $response->submitted_at?->toIso8601String(),
                'program_name' => trim((string) ($evaluation->abyipProgram?->program_name ?? $evaluation->title ?? 'Program')),
            ];
        });
    }

    private function openEvaluationQuery(User $user)
    {
        $today = Carbon::today()->toDateString();

        return $this->scopedEvaluationQuery($user)
            ->where('status', ProgramEvaluation::STATUS_OPEN)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            });
    }

    private function scopedEvaluationQuery(User $user)
    {
        $query = ProgramEvaluation::query();
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

    private function isEvaluationCurrentlyOpen(ProgramEvaluation $evaluation): bool
    {
        if (strtolower(trim((string) $evaluation->status)) !== ProgramEvaluation::STATUS_OPEN) {
            return false;
        }

        $closeDate = $evaluation->end_date ?? $evaluation->due_date;
        if ($closeDate !== null && Carbon::today()->gt($closeDate)) {
            return false;
        }

        return true;
    }

    private function userHasResponded(User $user, ProgramEvaluation $evaluation): bool
    {
        $registrationId = KabataanRegistration::query()
            ->where('user_id', $user->id)
            ->latest()
            ->value('id');

        if ($registrationId === null) {
            return false;
        }

        return ProgramEvaluationResponse::query()
            ->where('evaluation_id', $evaluation->id)
            ->where('registration_id', $registrationId)
            ->exists();
    }

    private function requireRegistration(User $user): KabataanRegistration
    {
        $registration = KabataanRegistration::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if ($registration === null) {
            throw ValidationException::withMessages([
                'registration' => ['KK Profiling registration is required before submitting evaluations.'],
            ]);
        }

        return $registration;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  list<array<string, mixed>>  $answers
     * @return list<array<string, mixed>>
     */
    private function validateAnswers(array $questions, array $answers): array
    {
        $normalized = [];
        foreach ($answers as $answer) {
            if (! is_array($answer)) {
                continue;
            }
            $questionId = (string) ($answer['question_id'] ?? '');
            if ($questionId !== '') {
                $normalized[$questionId] = $answer;
            }
        }

        $validated = [];
        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $questionId = (string) ($question['id'] ?? '');
            $label = trim((string) ($question['label'] ?? 'Question'));
            $required = (bool) ($question['required'] ?? false);
            $answer = $normalized[$questionId] ?? null;
            $value = is_array($answer) ? ($answer['answer'] ?? null) : null;

            if ($required && ($value === null || trim((string) $value) === '')) {
                throw ValidationException::withMessages([
                    "answers.{$questionId}" => ["{$label} is required."],
                ]);
            }

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $validated[] = [
                'question_id' => $questionId,
                'question_label' => $label,
                'question_type' => (string) ($question['type'] ?? 'text'),
                'answer' => $value,
            ];
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEvaluationSummary(ProgramEvaluation $evaluation, User $user, bool $withDetails = false): array
    {
        $isOpen = $this->isEvaluationCurrentlyOpen($evaluation);
        $hasResponded = $this->userHasResponded($user, $evaluation);
        $programName = trim((string) ($evaluation->abyipProgram?->program_name ?? $evaluation->title ?? 'Program'));
        $startDate = $evaluation->start_date?->format('Y-m-d');
        $endDate = ($evaluation->end_date ?? $evaluation->due_date)?->format('Y-m-d');

        $formatted = [
            'id' => $evaluation->id,
            'abyip_program_id' => $evaluation->abyip_program_id,
            'program_letter' => $evaluation->program_letter,
            'program_name' => $programName,
            'title' => $programName,
            'instructions' => $evaluation->instructions,
            'status' => $evaluation->status,
            'is_open' => $isOpen,
            'has_responded' => $hasResponded,
            'can_respond' => $isOpen && ! $hasResponded,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_date_display' => $this->formatDate($evaluation->start_date),
            'end_date_display' => $this->formatDate($evaluation->end_date ?? $evaluation->due_date),
        ];

        if ($withDetails) {
            $formatted['questions'] = is_array($evaluation->custom_questions) ? $evaluation->custom_questions : [];
        }

        return $formatted;
    }

    private function formatDate(?Carbon $date): string
    {
        if ($date === null) {
            return '—';
        }

        return $date->format('M j, Y');
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
}
