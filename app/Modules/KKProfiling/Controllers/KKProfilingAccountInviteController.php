<?php

namespace App\Modules\KKProfiling\Controllers;

use App\Http\Controllers\Controller;
use App\Services\KkProfilingAccountInviteService;
use App\Services\TurnstileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KKProfilingAccountInviteController extends Controller
{
    public function __construct(
        private readonly KkProfilingAccountInviteService $inviteService,
        private readonly TurnstileService $turnstileService,
    ) {}

    public function show(int $registration, string $token): View
    {
        try {
            $record = $this->inviteService->findValidRegistration($registration, $token);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'This activation link is invalid.';

            return view('kkprofiling::account-activate-error', [
                'message' => $message,
                'errorType' => $this->inviteService->errorTypeFromMessage((string) $message),
            ]);
        }

        return view('kkprofiling::account-activate', [
            'registration' => $record,
            'token' => $token,
            'email' => $record->email,
            'barangay' => $record->barangay?->name ?? 'your barangay',
        ]);
    }

    public function activate(Request $request, int $registration, string $token): RedirectResponse|JsonResponse|View
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        if ($fail = $this->turnstileService->requestFailed($request)) {
            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $fail,
                ], 422);
            }

            return back()->withErrors(['password' => $fail])->withInput();
        }

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

        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $record = $this->inviteService->findValidRegistration($registration, $token);
            $this->inviteService->activate($record, $token, (string) $request->input('password'));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Unable to activate this account.';

            if ($wantsJson) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($request->boolean('from_form') || $request->has('password')) {
                return back()->withErrors(['password' => $message])->withInput();
            }

            return view('kkprofiling::account-activate-error', [
                'message' => $message,
                'errorType' => $this->inviteService->errorTypeFromMessage((string) $message),
            ]);
        }

        $successMessage = 'Your account has been activated successfully. You can now sign in.';

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'activated' => true,
                'redirect_url' => route('sign-in'),
                'message' => $successMessage,
            ]);
        }

        return redirect()
            ->route('sign-in')
            ->with('success', $successMessage);
    }
}
