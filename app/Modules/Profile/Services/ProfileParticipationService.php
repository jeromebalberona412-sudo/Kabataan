<?php

namespace App\Modules\Profile\Services;

use App\Models\ProgramApplication;
use App\Models\User;
use App\Modules\Programs\Services\KabataanProgramService;
use App\Modules\Programs\Services\KabataanProgramSurveyService;

class ProfileParticipationService
{
    public function __construct(
        private readonly KabataanProgramService $programService,
        private readonly KabataanProgramSurveyService $surveyService,
    ) {
    }

    /**
     * @return array{
     *     programs: list<object>,
     *     summary: array<string, int>
     * }
     */
    public function getParticipationData(User $user): array
    {
        $applications = $this->programService->listUserApplications($user, false);
        $surveyResponses = $this->surveyService->listUserResponseDetails($user);

        $programs = [];

        foreach ($applications as $application) {
            $status = $this->mapApplicationStatus((string) ($application['status'] ?? 'pending'));
            $detailKey = 'application-'.($application['id'] ?? 0);

            $programs[] = (object) [
                'id' => $detailKey,
                'source' => 'application',
                'source_id' => (int) ($application['id'] ?? 0),
                'name' => $application['program_name'] ?? 'Program Application',
                'category' => $application['program_type'] ?? ($application['committee'] ?? 'Program'),
                'status' => $status,
                'created_at' => $application['submitted_at_iso'] ?? now()->toIso8601String(),
                'description' => $application['program_period'] ?? '',
                'answers_preview' => $this->buildApplicationAnswerPreview($application),
                'redirect_url' => $this->resolveApplicationRedirectUrl($application),
            ];
        }

        foreach ($surveyResponses as $response) {
            $detailKey = 'survey-'.($response['id'] ?? 0);

            $programs[] = (object) [
                'id' => $detailKey,
                'source' => 'survey',
                'source_id' => (int) ($response['id'] ?? 0),
                'name' => $response['program_name'] ?? 'Program Survey',
                'category' => 'Survey Response',
                'status' => 'completed',
                'created_at' => $response['submitted_at_iso'] ?? now()->toIso8601String(),
                'description' => $response['survey_period'] ?? '',
                'answers_preview' => $this->buildSurveyAnswerPreview($response['answers'] ?? []),
                'redirect_url' => $this->resolveSurveyRedirectUrl($response),
            ];
        }

        usort($programs, fn ($a, $b) => strcmp((string) $b->created_at, (string) $a->created_at));

        $summary = $this->buildSummary($programs);

        return [
            'programs' => $programs,
            'summary' => $summary,
        ];
    }

    private function resolveApplicationRedirectUrl(array $application): string
    {
        $letter = strtoupper((string) ($application['program_letter'] ?? ''));
        $scheduleId = (int) ($application['schedule_program_id'] ?? 0);

        if ($letter === 'I') {
            return $scheduleId > 0
                ? route('sports.apply.form', ['schedule' => $scheduleId])
                : route('sports.apply');
        }

        if ($letter === 'A' || $scheduleId > 0) {
            return $scheduleId > 0
                ? route('scholarship.apply.form', ['schedule' => $scheduleId])
                : route('scholarship.apply');
        }

        return route('scholarship.apply');
    }

    private function resolveSurveyRedirectUrl(array $response): string
    {
        $abyipProgramId = (int) ($response['abyip_program_id'] ?? 0);
        $surveyId = (int) ($response['survey_id'] ?? 0);

        if ($abyipProgramId > 0) {
            return route('programs.survey.landing', ['program' => $abyipProgramId]);
        }

        if ($surveyId > 0) {
            return route('programs.survey.form', ['survey' => $surveyId]);
        }

        return route('programs.survey.landing');
    }

    /**
     * @param  list<object>  $programs
     * @return array<string, int>
     */
    private function buildSummary(array $programs): array
    {
        return [
            'total' => count($programs),
            'approved' => count(array_filter($programs, fn ($p) => $p->status === 'approved')),
            'evaluation' => count(array_filter($programs, fn ($p) => $p->status === 'evaluation')),
            'completed' => count(array_filter($programs, fn ($p) => $p->status === 'completed')),
            'pending' => count(array_filter($programs, fn ($p) => $p->status === 'pending')),
        ];
    }

    private function mapApplicationStatus(string $status): string
    {
        return match ($status) {
            ProgramApplication::STATUS_APPROVED => 'approved',
            ProgramApplication::STATUS_REJECTED => 'declined',
            ProgramApplication::STATUS_CANCELLED => 'declined',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $application
     */
    private function buildApplicationAnswerPreview(array $application): string
    {
        $preview = $application['answers_preview'] ?? [];
        if ($preview === []) {
            return 'No answers recorded';
        }

        $first = $preview[0];
        $label = trim((string) ($first['label'] ?? 'Answer'));
        $value = trim((string) ($first['value'] ?? '—'));

        return $label !== '' ? "{$label}: {$value}" : $value;
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     */
    private function buildSurveyAnswerPreview(array $answers): string
    {
        if ($answers === []) {
            return 'Survey submitted';
        }

        $first = $answers[0]['answer'] ?? null;
        if (is_array($first)) {
            $first = implode(', ', $first);
        }

        return trim((string) ($first ?? 'Survey submitted'));
    }
}
