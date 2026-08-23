<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\Controller;
use App\Models\KabataanRegistration;
use App\Models\User;
use App\Modules\Authentication\Services\TrustedDeviceService;
use App\Services\KabataanAuthService;
use App\Services\RegistrationEvaluationService;
use App\Services\TurnstileService;
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

        return response(view('authentication::sign-in'))->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ]);
    }

    public function signin(Request $request)
    {
        // ── Turnstile verification ──────────────────────────────────────────
        if ($this->turnstileService->isEnabled()) {
            $token = (string) $request->input('cf-turnstile-response', '');

            if ($token === '') {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Please complete the security verification.'], 422);
                }

                return back()->withInput($request->only('email'))->with('sign_in_error', 'Please complete the security verification.');
            }

            if (! $this->turnstileService->verify($token, $request->ip())) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Security verification failed. Please try again.'], 422);
                }

                return back()->withInput($request->only('email'))->with('sign_in_error', 'Security verification failed. Please try again.');
            }
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
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => KabataanAuthService::SIGNIN_DENIED_MESSAGE], 422);
            }

            return back()->withInput($request->only('email'))->with('sign_in_error', KabataanAuthService::SIGNIN_DENIED_MESSAGE);
        }

        // ── Status checks ───────────────────────────────────────────────────
        // Load registration only when status requires it — single query, reused below
        $registration = null;
        $needsRegistration = in_array($user->status, [User::STATUS_PENDING_APPROVAL, 'REJECTED'], true);

        if ($needsRegistration) {
            $registration = KabataanRegistration::select(['id', 'user_id', 'evaluation_status', 'review_notes'])
                ->where('user_id', $user->id)
                ->latest('id')
                ->first();
        }

        if ($user->status === User::STATUS_PENDING_APPROVAL) {
            if ($registration && RegistrationEvaluationService::isAutoApprovedStatus($registration->evaluation_status)) {
                $user->update(['status' => User::STATUS_ACTIVE]);
            } else {
                $msg = 'Please wait for SK officials to verify your account. You will receive an email once your registration has been approved.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }

                return back()->withInput($request->only('email'))->with('sign_in_error', $msg);
            }
        }

        if ($user->status === 'REJECTED') {
            $reason = $registration?->review_notes
                ? 'Reason: '.$registration->review_notes
                : 'Please contact your SK officials for more information.';
            $msg = 'Your KK Profiling registration has been rejected. '.$reason;
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->withInput($request->only('email'))->with('sign_in_error', $msg);
        }

        if ($user->status === 'INACTIVE') {
            $msg = 'Your account has been deactivated. Please contact your SK officials.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->withInput($request->only('email'))->with('sign_in_error', $msg);
        }

        // ── Authenticate ────────────────────────────────────────────────────
        $remember = $request->boolean('remember');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $redirectUrl = redirect()->intended(route('dashboard'))->getTargetUrl();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => $redirectUrl]);
        }

        return redirect()->to($redirectUrl);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user instanceof User) {
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
            return response()->json(['success' => true, 'redirect' => route('sign-in')], 200, $headers);
        }

        return redirect()->route('sign-in')->withHeaders($headers);
    }

    public function showForgotPassword()
    {
        return view('authentication::forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        if ($fail = $this->turnstileService->requestFailed($request)) {
            return back()
                ->withInput($request->only('email'))
                ->with('forgot_password_error', $fail);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $this->kabataanAuthService->canAccessPortal($user)) {
            return back()
                ->withInput($request->only('email'))
                ->with('forgot_password_error', 'No account found with this email address. Please check your email and try again.');
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            $sentAt = now();

            $request->session()->put('kabataan_fp_verify', [
                'email' => (string) $request->email,
                'sent_at' => $sentAt->toIso8601String(),
                'resend_available_at' => $sentAt->copy()->addSeconds(60)->toIso8601String(),
                'expires_at' => $sentAt->copy()->addHours(2)->toIso8601String(),
            ]);

            return redirect()->route('password.verify-email');
        }

        return back()
            ->withInput($request->only('email'))
            ->with('forgot_password_error', $status === Password::RESET_THROTTLED
                ? 'You recently requested a password reset. Please wait 60 seconds before requesting another link.'
                : 'Unable to send a reset link to that email address. Please try again.'
            );
    }

    public function showForgotPasswordVerifyEmail(Request $request)
    {
        $state = $request->session()->get('kabataan_fp_verify');

        if (! is_array($state) || empty($state['email'])) {
            return redirect()->route('password.request');
        }

        $expiresAt = Carbon::parse((string) ($state['expires_at'] ?? now()->toIso8601String()));
        if ($expiresAt->isPast()) {
            $request->session()->forget('kabataan_fp_verify');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'Your password reset session has expired. Please start again.']);
        }

        $resendAvailableAt = Carbon::parse((string) ($state['resend_available_at'] ?? now()->toIso8601String()));

        return view('authentication::verify-email', [
            'email' => (string) $state['email'],
            'resendAvailableAt' => $resendAvailableAt->toIso8601String(),
            'resendCooldownSecs' => max(0, (int) now()->diffInSeconds($resendAvailableAt, false)),
        ]);
    }

    public function resendForgotPasswordEmail(Request $request): JsonResponse
    {
        if ($fail = $this->turnstileService->requestFailed($request)) {
            return response()->json([
                'ok' => false,
                'message' => $fail,
            ], 422);
        }

        $state = $request->session()->get('kabataan_fp_verify');

        if (! is_array($state) || empty($state['email'])) {
            return response()->json([
                'ok' => false,
                'message' => 'No active password reset session. Please start again.',
            ], 404);
        }

        $expiresAt = Carbon::parse((string) ($state['expires_at'] ?? now()->toIso8601String()));
        if ($expiresAt->isPast()) {
            $request->session()->forget('kabataan_fp_verify');

            return response()->json([
                'ok' => false,
                'message' => 'Your password reset session has expired. Please start again.',
                'expired' => true,
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
            ], 429);
        }

        $status = Password::sendResetLink(['email' => (string) $state['email']]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'ok' => false,
                'message' => __($status),
            ], 422);
        }

        $newResendAvailableAt = now()->addSeconds(60);

        $state['resend_available_at'] = $newResendAvailableAt->toIso8601String();
        $state['sent_at'] = now()->toIso8601String();
        $request->session()->put('kabataan_fp_verify', $state);

        return response()->json([
            'ok' => true,
            'message' => 'A new password reset link has been sent to your email.',
            'resend_available_at' => $newResendAvailableAt->toIso8601String(),
            'cooldown_remaining' => 60,
        ]);
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('authentication::reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
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
            return redirect()->route('sign-in')
                ->with('success', 'Your password has been reset. You can now sign in.');
        }

        throw ValidationException::withMessages([
            'email' => __($status),
        ]);
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
