<?php

namespace App\Modules\Programs\Controllers;

use App\Http\Controllers\Controller;
use App\Models\KabataanRegistration;
use App\Modules\Programs\Services\KabataanProgramEvaluationService;
use App\Modules\Programs\Services\KabataanProgramService;
use App\Modules\Programs\Services\KabataanProgramSurveyService;
use App\Modules\Programs\Services\ProgramDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramController extends Controller
{
    public function __construct(
        private readonly KabataanProgramService $programService,
        private readonly ProgramDocumentService $documentService,
        private readonly KabataanProgramSurveyService $surveyService,
        private readonly KabataanProgramEvaluationService $evaluationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        return response()->json($this->programService->getDashboardPayload($user));
    }

    public function showSchedule(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $program = $this->programService->getScheduleProgramForUser($id, $user);

        if ($program === null) {
            return response()->json(['message' => 'Program not found.'], 404);
        }

        return response()->json($program);
    }

    public function listApplications(Request $request): JsonResponse
    {
        $user = Auth::user();
        $letter = $request->query('letter');

        return response()->json([
            'applications' => $this->programService->listUserApplications(
                $user,
                false,
                is_string($letter) ? $letter : null,
            ),
        ]);
    }

    public function showApplication(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'application' => $this->programService->getUserApplication($user, $id),
        ]);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_program_id' => ['required', 'integer'],
            'question_id' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.ProgramDocumentService::MAX_FILE_SIZE_KB],
        ]);

        $user = Auth::user();
        $document = $this->documentService->uploadDraft(
            $user,
            (int) $validated['schedule_program_id'],
            $validated['question_id'],
            $request->file('file'),
        );

        return response()->json([
            'message' => 'PDF uploaded successfully.',
            'document' => $document,
        ]);
    }

    public function showDocument(Request $request, int $scheduleProgramId, string $questionId): StreamedResponse
    {
        $user = Auth::user();
        $download = $request->boolean('download');

        return $this->documentService->resolveDocumentForUser($user, $scheduleProgramId, $questionId, $download);
    }

    public function cancelApplication(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $user = Auth::user();
        $application = $this->programService->cancelApplication(
            $user,
            $id,
            $validated['cancel_reason'],
        );

        return response()->json([
            'message' => 'Application cancelled successfully. You may submit a new application.',
            'application' => $application,
        ]);
    }

    public function submitApplication(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_program_id' => ['required', 'integer'],
            'answers' => ['present', 'array'],
            'answers.*.question_id' => ['required', 'string'],
            'answers.*.question_type' => ['nullable', 'string'],
            'answers.*.question_label' => ['nullable', 'string'],
            'answers.*.answer' => ['nullable'],
            'system_field_answers' => ['nullable', 'array'],
        ]);

        $user = Auth::user();
        $application = $this->programService->submitApplication(
            $user,
            (int) $validated['schedule_program_id'],
            $validated['answers'],
            $validated['system_field_answers'] ?? [],
        );

        return response()->json([
            'message' => 'Application submitted successfully.',
            'application' => $application,
        ], 201);
    }

    public function scholarshipLanding(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return redirect()->guest(route('sign-in'));
        }

        $scheduleId = (int) $request->query('schedule', 0);

        $registration = KabataanRegistration::with('barangay')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $barangayName = $registration?->barangay?->name ?? 'Your Barangay';

        return view('programs::scholarship_landing', [
            'scheduleProgramId' => $scheduleId > 0 ? $scheduleId : null,
            'barangayName' => $barangayName,
            'kkFieldLabels' => $this->programService->kkFieldLabels(),
        ]);
    }

    public function scholarshipForm(Request $request): RedirectResponse
    {
        $scheduleId = (int) $request->query('schedule', 0);

        if ($scheduleId <= 0) {
            abort(404);
        }

        return redirect()->route('scholarship.apply', ['schedule' => $scheduleId]);
    }

    public function sportsLanding(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            return redirect()->guest(route('sign-in'));
        }

        $scheduleId = (int) $request->query('schedule', 0);

        if ($scheduleId > 0) {
            return redirect()->route('sports.apply.form', ['schedule' => $scheduleId]);
        }

        $registration = KabataanRegistration::with('barangay')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        return view('programs::sports_landing', [
            'scheduleProgramId' => null,
            'barangayName' => $registration?->barangay?->name ?? 'Your Barangay',
            'kkFieldLabels' => $this->programService->kkFieldLabels(),
            'programsPayload' => $this->programService->getDashboardPayload($user),
        ]);
    }

    public function sportsForm(Request $request): View
    {
        $user = Auth::user();
        $scheduleId = (int) $request->query('schedule', 0);

        if ($scheduleId <= 0) {
            abort(404);
        }

        $program = $this->programService->getScheduleProgramForUser($scheduleId, $user);
        if ($program === null || ($program['program_letter'] ?? '') !== 'I') {
            abort(404);
        }

        return view('programs::sports-registration', [
            'scheduleProgramId' => $scheduleId,
            'program' => $program,
            'kkFieldLabels' => $this->programService->kkFieldLabels(),
            'backRoute' => route('sports.apply'),
        ]);
    }

    public function surveyLandingHome(Request $request): View|RedirectResponse
    {
        $abyipProgramId = (int) $request->query('program', 0);
        if ($abyipProgramId > 0) {
            return redirect()->route('programs.survey.landing', ['program' => $abyipProgramId]);
        }

        return $this->renderSurveyLanding(null);
    }

    public function surveyLanding(Request $request, int $program): View
    {
        return $this->renderSurveyLanding($program > 0 ? $program : null);
    }

    public function surveyFormLegacy(Request $request): RedirectResponse
    {
        $surveyId = (int) $request->query('survey', 0);
        if ($surveyId <= 0) {
            abort(404);
        }

        return redirect()->route('programs.survey.form', ['survey' => $surveyId]);
    }

    public function surveyForm(Request $request, int $survey): View
    {
        $user = Auth::user();

        if ($survey <= 0) {
            abort(404);
        }

        $surveyData = $this->surveyService->getSurveyForUser($user, $survey);
        if ($surveyData === null || ! ($surveyData['can_respond'] ?? false)) {
            abort(404);
        }

        return view('programs::program_survey_form', [
            'surveyId' => $survey,
            'survey' => $surveyData,
            'landingUrl' => $this->surveyLandingUrl($surveyData['abyip_program_id'] ?? null),
            'submitUrl' => route('kabataan.programs.survey-responses.store'),
        ]);
    }

    public function surveyResponse(Request $request, int $response): View
    {
        $user = Auth::user();

        if ($response <= 0) {
            abort(404);
        }

        try {
            $responseData = $this->surveyService->getUserResponse($user, $response);
        } catch (ValidationException) {
            abort(404);
        }

        return view('programs::program_survey_response', [
            'response' => $responseData,
            'landingUrl' => $this->surveyLandingUrl($responseData['abyip_program_id'] ?? null),
        ]);
    }

    private function renderSurveyLanding(?int $abyipProgramId): View
    {
        $user = Auth::user();

        $registration = KabataanRegistration::with('barangay')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $barangayName = $registration?->barangay?->name ?? 'Your Barangay';

        return view('programs::program_survey_landing', [
            'abyipProgramId' => $abyipProgramId,
            'barangayName' => $barangayName,
            'surveyByProgramUrl' => $abyipProgramId
                ? route('kabataan.programs.surveys.by-program', ['abyipProgramId' => $abyipProgramId])
                : null,
            'surveyResponsesUrl' => route('kabataan.programs.survey-responses.index', array_filter([
                'program' => $abyipProgramId,
            ])),
        ]);
    }

    private function surveyLandingUrl(mixed $abyipProgramId): string
    {
        $programId = (int) ($abyipProgramId ?? 0);

        return $programId > 0
            ? route('programs.survey.landing', ['program' => $programId])
            : route('programs.survey.home');
    }

    public function showSurvey(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $survey = $this->surveyService->getSurveyForUser($user, $id);

        if ($survey === null) {
            return response()->json(['message' => 'Survey not found.'], 404);
        }

        return response()->json(['survey' => $survey]);
    }

    public function showSurveyByProgram(Request $request, int $abyipProgramId): JsonResponse
    {
        $user = Auth::user();
        $survey = $this->surveyService->getOpenSurveyByProgram($user, $abyipProgramId)
            ?? $this->surveyService->getLatestSurveyByProgram($user, $abyipProgramId);

        if ($survey === null) {
            return response()->json(['message' => 'No survey found for this program.'], 404);
        }

        return response()->json(['survey' => $survey]);
    }

    public function listSurveyResponses(Request $request): JsonResponse
    {
        $user = Auth::user();
        $abyipProgramId = (int) $request->query('program', 0);

        return response()->json([
            'responses' => $this->surveyService->listUserResponses(
                $user,
                $abyipProgramId > 0 ? $abyipProgramId : null,
            ),
        ]);
    }

    public function showSurveyResponse(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            return response()->json([
                'response' => $this->surveyService->getUserResponse($user, $id),
            ]);
        } catch (ValidationException) {
            return response()->json([
                'message' => 'Survey response not found.',
            ], 404);
        }
    }

    public function submitSurveyResponse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'survey_id' => ['required', 'integer'],
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable'],
        ]);

        $user = Auth::user();

        try {
            $response = $this->surveyService->submitResponse(
                $user,
                (int) $validated['survey_id'],
                $validated['answers'],
            );

            return response()->json([
                'message' => 'Survey submitted successfully.',
                'response' => $response,
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    public function evaluationForm(Request $request): View
    {
        $user = Auth::user();
        $evaluationId = (int) $request->query('evaluation', 0);

        if ($evaluationId <= 0) {
            abort(404);
        }

        $evaluation = $this->evaluationService->getEvaluationForUser($user, $evaluationId);
        if ($evaluation === null || ! ($evaluation['can_respond'] ?? false)) {
            abort(404);
        }

        return view('programs::program_evaluation_form', [
            'evaluationId' => $evaluationId,
            'evaluation' => $evaluation,
        ]);
    }

    public function showEvaluation(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $evaluation = $this->evaluationService->getEvaluationForUser($user, $id);

        if ($evaluation === null) {
            return response()->json(['message' => 'Evaluation not found.'], 404);
        }

        return response()->json(['evaluation' => $evaluation]);
    }

    public function showEvaluationByProgram(Request $request, int $abyipProgramId): JsonResponse
    {
        $user = Auth::user();
        $evaluation = $this->evaluationService->getOpenEvaluationByProgram($user, $abyipProgramId);

        if ($evaluation === null) {
            return response()->json(['message' => 'No open evaluation found for this program.'], 404);
        }

        return response()->json(['evaluation' => $evaluation]);
    }

    public function submitEvaluationResponse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'evaluation_id' => ['required', 'integer'],
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'string'],
            'answers.*.answer' => ['nullable'],
        ]);

        $user = Auth::user();

        try {
            $response = $this->evaluationService->submitResponse(
                $user,
                (int) $validated['evaluation_id'],
                $validated['answers'],
            );

            return response()->json([
                'message' => 'Evaluation submitted successfully. Thank you for your feedback.',
                'response' => $response,
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        }
    }
}
