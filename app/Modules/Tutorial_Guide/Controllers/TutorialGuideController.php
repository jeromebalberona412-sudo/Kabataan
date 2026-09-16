<?php

namespace App\Modules\Tutorial_Guide\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tutorial_Guide\Models\KabataanTutorial;
use App\Modules\Tutorial_Guide\Services\TutorialGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TutorialGuideController extends Controller
{
    public function __construct(
        protected TutorialGuideService $tutorialService
    ) {}

    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tutorialKey = (string) $request->input('tutorial_key', KabataanTutorial::DEFAULT_TUTORIAL_KEY);

        try {
            $tutorial = $this->tutorialService->getOrCreateTutorial($user, $tutorialKey);

            return response()->json([
                'success' => true,
                'tutorial' => $this->tutorialService->formatTutorialPayload($tutorial),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to load tutorial status.',
            ], 500);
        }
    }

    public function start(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tutorialKey = (string) $request->input('tutorial_key', KabataanTutorial::DEFAULT_TUTORIAL_KEY);

        try {
            $tutorial = $this->tutorialService->startTutorial($user, $tutorialKey);

            return response()->json([
                'success' => true,
                'tutorial' => $this->tutorialService->formatTutorialPayload($tutorial),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to start tutorial. Please try again.',
            ], 500);
        }
    }

    public function progress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:50'],
            'tutorial_key' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $tutorialKey = (string) ($validated['tutorial_key'] ?? KabataanTutorial::DEFAULT_TUTORIAL_KEY);
        $step = (int) $validated['step'];

        try {
            $tutorial = $this->tutorialService->updateProgress($user, $step, $tutorialKey);

            return response()->json([
                'success' => true,
                'tutorial' => $this->tutorialService->formatTutorialPayload($tutorial),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to save tutorial progress.',
            ], 500);
        }
    }

    public function complete(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tutorialKey = (string) $request->input('tutorial_key', KabataanTutorial::DEFAULT_TUTORIAL_KEY);

        try {
            $tutorial = $this->tutorialService->completeTutorial($user, $tutorialKey);

            return response()->json([
                'success' => true,
                'message' => 'Tutorial completed successfully.',
                'tutorial' => $this->tutorialService->formatTutorialPayload($tutorial),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to complete tutorial.',
            ], 500);
        }
    }

    public function skip(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tutorialKey = (string) $request->input('tutorial_key', KabataanTutorial::DEFAULT_TUTORIAL_KEY);

        try {
            $tutorial = $this->tutorialService->skipTutorial($user, $tutorialKey);

            return response()->json([
                'success' => true,
                'message' => 'Tutorial closed.',
                'tutorial' => $this->tutorialService->formatTutorialPayload($tutorial),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Please finish the tutorial first.',
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to skip tutorial.',
            ], 500);
        }
    }

    public function reset(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tutorialKey = (string) $request->input('tutorial_key', KabataanTutorial::DEFAULT_TUTORIAL_KEY);

        try {
            $tutorial = $this->tutorialService->resetTutorial($user, $tutorialKey);

            return response()->json([
                'success' => true,
                'message' => 'Tutorial restarted.',
                'tutorial' => $this->tutorialService->formatTutorialPayload($tutorial),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to reset tutorial.',
            ], 500);
        }
    }
}
