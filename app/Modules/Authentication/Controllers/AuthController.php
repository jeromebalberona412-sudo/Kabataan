<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SessionTimeout;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Modules\Authentication\Services\TrustedDeviceService;
use App\Services\KabataanAuthService;
use App\Services\RegistrationEvaluationService;
use App\Services\TurnstileAttemptGuard;
use App\Services\TurnstileService;
use App\Support\MailUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly KabataanAuthService $kabataanAuthService,
        private readonly TurnstileService $turnstileService,
        private readonly TurnstileAttemptGuard $turnstileGuard,
        private readonly TrustedDeviceService $trustedDeviceService,
    ) {}

    public function showSignin()
    {
        if (Auth::check()) {
            if ($this->kabataanAuthService->canAccessPortal(Auth::user())) {
                return redirect()->route('dashboard');
            }

            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $request = request();

        return response(view('authentication::sign-in', [
            'turnstileRequired' => $this->turnstileGuard->isRequired(
                TurnstileAttemptGuard::ACTION_SIGNIN,
                $request
            ),
        ]))->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ]);
    }

    public function signin(Request $request)
    {
        if ($fail = $this->turnstileGuard->enforce(TurnstileAttemptGuard::ACTION_SIGNIN, $request)) {
            return $this->signinFailureResponse($request, $fail, true);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Select only the columns needed for auth — avoids loading large unused fields
        $user = User::select([
            'id', 'email', 'password', 'role', 'status',
            'name', 'barangay_id', 'tenant_id',
            'last_login_at', 'last_login_ip',
            'email_verified_at', 'remember_token',
        ])
            ->where('email', $credentials['email'])
            ->first();

        if (
            ! $user
            || ! Hash::check($credentials['password'], $user->password)
            || ! $this->kabataanAuthService->canAccessPortal($user)
        ) {
            $attempt = $this->turnstileGuard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);
            $message = $attempt['message'] ?? KabataanAuthService::SIGNIN_DENIED_MESSAGE;

            return $this->signinFailureResponse($request, $message, (bool) $attempt['turnstile_required']);
        }

        // ── Status checks ───────────────────────────────────────────────────
        // Load latest registration
        $registration = KabataanRegistration::select(['id', 'user_id', 'status', 'evaluation_status', 'review_notes', 'rejection_reason', 'rejection_remarks', 'email', 'barangay_id'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->latest('id')
            ->first();

        if ($user->status === 'REJECTED' || ($registration && $registration->status === 'rejected')) {
            $reason = $registration?->rejection_reason ?: ($registration?->review_notes ?: 'Incorrect or incomplete information');
            $remarks = $registration?->rejection_remarks;
            $msg = 'Your KK Profiling registration has been rejected. Reason: '.$reason.($remarks ? ' (Remarks: '.$remarks.')' : '');
            $attempt = $this->turnstileGuard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'rejection' => [
                        'reason' => $reason,
                        'remarks' => $remarks,
                        'email' => $user->email,
                    ],
                ], 422);
            }

            return back()
                ->withInput($request->only('email'))
                ->with('sign_in_error', $msg)
                ->with('rejection_data', [
                    'reason' => $reason,
                    'remarks' => $remarks,
                    'email' => $user->email,
                ]);
        }

        if ($user->status === User::STATUS_PENDING_APPROVAL) {
            if ($registration && RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status)) {
                $user->update(['status' => User::STATUS_ACTIVE]);
            } else {
                $msg = 'Please wait for SK officials to verify your account. You will receive an email once your registration has been approved.';
                $attempt = $this->turnstileGuard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);

                return $this->signinFailureResponse(
                    $request,
                    $attempt['message'] ?? $msg,
                    (bool) $attempt['turnstile_required']
                );
            }
        }

        if ($user->status === 'INACTIVE') {
            $msg = 'Your account has been deactivated. Please contact your SK officials.';
            $attempt = $this->turnstileGuard->recordFailure(TurnstileAttemptGuard::ACTION_SIGNIN, $request);

            return $this->signinFailureResponse(
                $request,
                $attempt['message'] ?? $msg,
                (bool) $attempt['turnstile_required']
            );
        }

        // ── Authenticate ────────────────────────────────────────────────────
        $this->turnstileGuard->clear(TurnstileAttemptGuard::ACTION_SIGNIN, $request);

        $remember = $request->boolean('remember');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put(
            SessionTimeout::SESSION_KEY,
            now()->getTimestamp()
        );

        $redirectUrl = MailUrl::sameOrigin(redirect()->intended(route('dashboard'))->getTargetUrl());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => $redirectUrl]);
        }

        return redirect()->to($redirectUrl);
    }

    private function signinFailureResponse(Request $request, string $message, bool $turnstileRequired = false)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'turnstile_required' => $turnstileRequired || $this->turnstileGuard->isRequired(
                    TurnstileAttemptGuard::ACTION_SIGNIN,
                    $request
                ),
            ], 422);
        }

        return back()
            ->withInput($request->only('email'))
            ->with('sign_in_error', $message)
            ->with('turnstile_required', $turnstileRequired || $this->turnstileGuard->isRequired(
                TurnstileAttemptGuard::ACTION_SIGNIN,
                $request
            ));
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user instanceof User) {
            try {
                app(\App\Modules\Communications\Services\CallService::class)->endActiveCallsForUser($user);
            } catch (\Throwable) {
                // Do not block logout if communications is unavailable.
            }
            $this->trustedDeviceService->revokeCurrentDevice($user, $request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'redirect' => MailUrl::sameOrigin(route('sign-in'))], 200, $headers);
        }

        return redirect()->route('sign-in')->withHeaders($headers);
    }

    public function continueSession(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'last_activity_at' => (int) $request->session()->get(
                SessionTimeout::SESSION_KEY,
                now()->getTimestamp()
            ),
            'timeout_minutes' => max(1, (int) config('session.timeout', 120)),
        ]);
    }

    public function showForgotPassword()
    {
        return view('authentication::forgot-password', [
            'turnstileRequired' => $this->turnstileGuard->isRequired(
                TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD,
                request()
            ),
        ]);
    }

    public function sendResetLink(Request $request)
    {
        if ($fail = $this->turnstileGuard->enforce(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request)) {
            return back()
                ->withInput($request->only('email'))
                ->with('forgot_password_error', $fail)
                ->with('turnstile_required', true);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $this->kabataanAuthService->canAccessPortal($user)) {
            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request);

            return back()
                ->withInput($request->only('email'))
                ->with('forgot_password_error', 'Invalid email.')
                ->with('turnstile_required', (bool) $attempt['turnstile_required']);
        }

        $status = Password::RESET_LINK_SENT;

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->with('forgot_password_error', 'We could not send the password reset email. Please try again later.')
                ->with('turnstile_required', true);
        }

        if ($status === Password::RESET_THROTTLED) {
            $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request);

            return back()
                ->withInput($request->only('email'))
                ->with('forgot_password_error', 'Too many attempts. Please wait a moment before trying again.')
                ->with(
                    'turnstile_required',
                    $this->turnstileGuard->isRequired(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request)
                );
        }

        if ($status === Password::RESET_LINK_SENT) {
            $sentAt = now();

            $request->session()->put('kabataan_fp_verify', [
                'email' => (string) $request->email,
                'sent_at' => $sentAt->toIso8601String(),
                'resend_available_at' => $sentAt->copy()->addSeconds(60)->toIso8601String(),
                'expires_at' => $sentAt->copy()->addHours(2)->toIso8601String(),
            ]);

            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request);

            return redirect()
                ->route('password.verify-email')
                ->with('turnstile_required', (bool) $attempt['turnstile_required']);
        }

        $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request);

        return back()
            ->withInput($request->only('email'))
            ->with('forgot_password_error', 'Unable to send a reset link to that email address. Please try again.')
            ->with('turnstile_required', (bool) $attempt['turnstile_required']);
    }

    public function showForgotPasswordVerifyEmail(Request $request)
    {
        $state = $request->session()->get('kabataan_fp_verify');

        if (! is_array($state) || empty($state['email'])) {
            return redirect()->route('sign-in')
                ->with('sign_in_error', 'CSRF token mismatch. Your password has already been reset successfully. Please sign in using your new password.');
        }

        $expiresAt = Carbon::parse((string) ($state['expires_at'] ?? now()->toIso8601String()));
        if ($expiresAt->isPast()) {
            $request->session()->forget('kabataan_fp_verify');

            return redirect()->route('sign-in')
                ->with('sign_in_error', 'CSRF token mismatch. Your password has already been reset successfully. Please sign in using your new password.');
        }

        $resendAvailableAt = Carbon::parse((string) ($state['resend_available_at'] ?? now()->toIso8601String()));

        return view('authentication::verify-email', [
            'email' => (string) $state['email'],
            'resendAvailableAt' => $resendAvailableAt->toIso8601String(),
            'resendCooldownSecs' => max(0, (int) now()->diffInSeconds($resendAvailableAt, false)),
            'turnstileRequired' => $this->turnstileGuard->isRequired(
                TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD,
                $request
            ),
        ]);
    }

    public function resendForgotPasswordEmail(Request $request): JsonResponse
    {
        if ($fail = $this->turnstileGuard->enforce(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request)) {
            return response()->json([
                'ok' => false,
                'message' => $fail,
                'turnstile_required' => true,
            ], 422);
        }

        $state = $request->session()->get('kabataan_fp_verify');

        if (! is_array($state) || empty($state['email'])) {
            return response()->json([
                'ok' => false,
                'message' => 'CSRF token mismatch. Your password has already been reset successfully. Please sign in using your new password.',
                'expired' => true,
                'already_reset' => true,
            ], 410);
        }

        $expiresAt = Carbon::parse((string) ($state['expires_at'] ?? now()->toIso8601String()));
        if ($expiresAt->isPast()) {
            $request->session()->forget('kabataan_fp_verify');

            return response()->json([
                'ok' => false,
                'message' => 'CSRF token mismatch. Your password has already been reset successfully. Please sign in using your new password.',
                'expired' => true,
                'already_reset' => true,
            ], 410);
        }

        $resendAvailableAt = Carbon::parse((string) ($state['resend_available_at'] ?? now()->toIso8601String()));
        $remainingSecs = (int) now()->diffInSeconds($resendAvailableAt, false);

        if ($remainingSecs > 0) {
            return response()->json([
                'ok' => false,
                'message' => "Please wait {$remainingSecs} seconds before resending.",
                'resend_available_at' => $resendAvailableAt->toIso8601String(),
                'cooldown_remaining' => $remainingSecs,
                'turnstile_required' => $this->turnstileGuard->isRequired(
                    TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD,
                    $request
                ),
            ], 429);
        }

        try {
            $status = Password::sendResetLink(['email' => (string) $state['email']]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'We could not send the password reset email. Please try again later.',
                'turnstile_required' => true,
            ], 422);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request);

            return response()->json([
                'ok' => false,
                'message' => $status === Password::RESET_THROTTLED
                    ? 'Too many attempts. Please wait a moment before trying again.'
                    : __($status),
                'turnstile_required' => (bool) $attempt['turnstile_required'],
            ], $status === Password::RESET_THROTTLED ? 429 : 422);
        }

        $newResendAvailableAt = now()->addSeconds(60);

        $state['resend_available_at'] = $newResendAvailableAt->toIso8601String();
        $state['sent_at'] = now()->toIso8601String();
        $request->session()->put('kabataan_fp_verify', $state);

        $attempt = $this->turnstileGuard->recordRequest(TurnstileAttemptGuard::ACTION_FORGOT_PASSWORD, $request);

        return response()->json([
            'ok' => true,
            'message' => 'A new password reset link has been sent to your email.',
            'resend_available_at' => $newResendAvailableAt->toIso8601String(),
            'cooldown_remaining' => 60,
            'turnstile_required' => (bool) $attempt['turnstile_required'],
        ]);
    }

    public function showResetPassword(Request $request, string $token)
    {
        $email = trim((string) $request->query('email', ''));
        $tokenValid = $this->isValidPasswordResetToken($email, $token);

        return view('authentication::reset-password', [
            'token' => $token,
            'email' => $email,
            'tokenValid' => $tokenValid,
            'tokenError' => $tokenValid
                ? null
                : 'Invalid or expired password reset link. Please request a new one.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        if ($fail = $this->turnstileService->requestFailed($request)) {
            return back()->withErrors(['email' => $fail])->withInput($request->except('password', 'password_confirmation'));
        }

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $this->kabataanAuthService->canAccessPortal($user)) {
            throw ValidationException::withMessages([
                'email' => 'We could not reset the password for this account.',
            ]);
        }

        if (! Password::broker()->tokenExists($user, (string) $request->input('token'))) {
            throw ValidationException::withMessages([
                'email' => 'Invalid or expired password reset link. Please request a new one.',
            ]);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $resetUser, string $password) {
                if (! $this->kabataanAuthService->canAccessPortal($resetUser)) {
                    return;
                }

                $resetUser->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => null,
                ])->save();

                $this->trustedDeviceService->revokeAllForUser($resetUser);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $request->session()->forget('kabataan_fp_verify');

            return redirect()->route('sign-in')
                ->with('success', 'Your password has been reset. You can now sign in.');
        }

        $message = $status === Password::INVALID_TOKEN
            ? 'Invalid or expired password reset link. Please request a new one.'
            : __($status);

        throw ValidationException::withMessages([
            'email' => $message,
        ]);
    }

    private function isValidPasswordResetToken(string $email, string $token): bool
    {
        if ($email === '' || $token === '') {
            return false;
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! $this->kabataanAuthService->canAccessPortal($user)) {
            return false;
        }

        return Password::broker()->tokenExists($user, $token);
    }

    // Keep these for route compatibility (prototype routes still registered)
    public function showRegister()
    {
        return redirect()->route('kkprofiling.signup');
    }

    public function showEmailVerification(Request $request)
    {
        return view('authentication::email-verification', [
            'email' => $request->query('email', ''),
        ]);
    }

    public function sendVerificationEmail(Request $request)
    {
        if ($fail = $this->turnstileService->requestFailed($request)) {
            return response()->json(['success' => false, 'message' => $fail], 422);
        }

        return response()->json(['success' => true]);
    }

    public function resendVerificationEmail(Request $request)
    {
        if ($fail = $this->turnstileService->requestFailed($request)) {
            return response()->json(['success' => false, 'message' => $fail], 422);
        }

        return response()->json(['success' => true]);
    }

    public function checkVerificationStatus(Request $request)
    {
        return response()->json(['verified' => false]);
    }

    public function verifyEmail($token, Request $request)
    {
        return view('authentication::verify-success');
    }

    public function showTestEmailVerification()
    {
        return view('authentication::test-email-verification');
    }
}
