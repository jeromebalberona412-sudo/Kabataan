<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Profile\Services\EmailChangeService;
use App\Modules\Profile\Services\PasswordChangeService;
use App\Modules\Profile\Services\ProfileImageService;
use App\Modules\Profile\Services\ProfileParticipationService;
use App\Modules\Profile\Services\ProfileService;
use App\Modules\Profile\Services\ProfileSupportingDocumentsService;
use App\Rules\ValidEmailAddress;
use App\Support\MailUrl;
use App\Support\SupportingDocumentTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const PASSWORD_CHANGE_CONFIRMED_SESSION_KEY = 'password_change_confirmed_this_session';

    private const EMAIL_CHANGE_CONFIRMED_SESSION_KEY = 'email_change_confirmed_this_session';

    public function __construct(
        private readonly ProfileService $profileService,
        private readonly EmailChangeService $emailChangeService,
        private readonly PasswordChangeService $passwordChangeService,
        private readonly ProfileParticipationService $participationService,
        private readonly ProfileImageService $profileImageService,
        private readonly ProfileSupportingDocumentsService $supportingDocumentsService,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('sign-in')->with('error', 'Please login first.');
        }

        $user = Auth::user();
        $display = $this->profileService->getDisplayData($user);
        $participation = $this->participationService->getParticipationData($user);
        $freshUser = $user->fresh();

        return view('profile::profile', [
            'user' => $freshUser,
            'profile' => $display,
            'kabataanRegistration' => $display['registration'],
            'barangayName' => $display['barangayName'],
            'barangayLogoUrl' => $display['barangayLogoUrl'],
            'fullName' => $display['fullName'],
            'profileImageUrl' => $this->profileImageService->resolveDisplayUrl($freshUser, $display['fullName']),
            'profileImageFallbackUrl' => $this->profileImageService->defaultAvatarUrl($freshUser, $display['fullName']),
            'canChangeProfileImage' => $this->profileImageService->canChangeProfileImage($freshUser),
            'profileImageNextChangeDisplay' => $this->profileImageService->nextChangeDisplayDate($freshUser),
            'programs' => collect($participation['programs']),
            'totalPrograms' => $participation['summary']['total'],
            'approvedPrograms' => $participation['summary']['approved'],
            'evaluationPrograms' => $participation['summary']['pending'],
            'completedPrograms' => $participation['summary']['completed'],
            'supportingDocuments' => $display['supportingDocuments'] ?? [],
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ]);
    }

    public function showChangeEmail(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('sign-in')->with('error', 'Please login first.');
        }

        $user = $request->user()->fresh();

        return view('profile::change-email', ['user' => $user])->withHeaders($this->noCacheHeaders());
    }

    public function requestChangeEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_email' => ['required', 'email', 'max:255'],
            'new_email' => ['required', 'string', 'max:'.ValidEmailAddress::MAX_LENGTH, 'different:current_email', new ValidEmailAddress],
            'password' => ['required', 'string', 'max:64'],
        ]);

        try {
            $this->emailChangeService->requestChange(
                $request->user(),
                $validated['current_email'],
                $validated['new_email'],
                $validated['password'],
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('change-email.verify')
            ->with('status', 'Verification link sent to your new email address.');
    }

    public function showChangeEmailVerify(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('sign-in')->with('error', 'Please login first.');
        }

        $user = $request->user()->fresh();

        if (! $this->emailChangeService->hasPendingChange($user)) {
            if ($this->emailChangeService->hasPendingPasswordSet($user)) {
                try {
                    $user = $this->emailChangeService->completePendingPasswordSet($user);
                } catch (ValidationException $exception) {
                    return $this->redirectAfterEmailChangeConfirmFailure($request, (int) $user->id, $exception);
                }

                return $this->finishEmailChangeSuccess($request, $user);
            }

            if ($this->emailChangeCompletedInThisSession($request)) {
                return redirect()
                    ->route('dashboard')
                    ->with('success', 'Email changed successfully.');
            }

            if ($this->wasEmailChangeConfirmed($request, $user)) {
                return $this->finishEmailChangeLogout($request, $user);
            }

            $request->session()->forget('email_change_verify_active');

            $redirect = redirect()->route('change-email');

            if ($request->session()->has('error')) {
                $redirect->with('error', $request->session()->get('error'));
            }

            if ($request->session()->has('errors')) {
                $redirect->withErrors($request->session()->get('errors'));
            }

            return $redirect;
        }

        $request->session()->put('email_change_verify_active', true);

        return view('profile::change-email-verify', [
            'user' => $user,
            'resendCooldown' => $this->emailChangeService->resendCooldownRemaining($user),
        ])->withHeaders($this->noCacheHeaders());
    }

    public function checkChangeEmailVerifyStatus(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();

        if ($this->emailChangeService->hasPendingChange($user)) {
            return response()->json([
                'state' => 'pending',
                'resend_cooldown' => $this->emailChangeService->resendCooldownRemaining($user),
            ]);
        }

        if ($this->emailChangeService->hasPendingPasswordSet($user)) {
            try {
                $user = $this->emailChangeService->completePendingPasswordSet($user);
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first()
                    ?: 'Email change request is no longer active.';

                return response()->json([
                    'state' => 'cancelled',
                    'redirect' => MailUrl::sameOrigin(route('change-email')),
                    'message' => $message,
                ]);
            }

            $request->session()->put(self::EMAIL_CHANGE_CONFIRMED_SESSION_KEY, true);
            $request->session()->forget('email_change_verify_active');

            return response()->json([
                'state' => 'completed',
                'redirect' => MailUrl::sameOrigin(route('dashboard')),
                'message' => 'Email changed successfully. Taking you to your dashboard...',
            ]);
        }

        if ($this->emailChangeCompletedInThisSession($request)) {
            $request->session()->forget('email_change_verify_active');

            return response()->json([
                'state' => 'completed',
                'redirect' => MailUrl::sameOrigin(route('dashboard')),
                'message' => 'Email changed successfully. Taking you to your dashboard...',
            ]);
        }

        if ($this->wasEmailChangeConfirmed($request, $user)) {
            $request->session()->forget('email_change_verify_active');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'state' => 'completed',
                'redirect' => MailUrl::sameOrigin(route('sign-in')),
                'message' => 'Email changed successfully. Please sign in with your new email.',
            ]);
        }

        $deliveryFailedMessage = $this->emailChangeService->pullDeliveryFailedMessage((int) $user->id);

        return response()->json([
            'state' => 'cancelled',
            'redirect' => MailUrl::sameOrigin(route('change-email')),
            'invalid_email' => $deliveryFailedMessage !== null,
            'message' => $deliveryFailedMessage
                ?? 'Email change request is no longer active.',
        ]);
    }

    public function resendChangeEmail(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $this->emailChangeService->resend($request->user()->fresh());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'Unable to resend verification email. Please try again.';

            $user = $request->user()->fresh();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'resend_cooldown' => $this->emailChangeService->resendCooldownRemaining($user),
                ], 422);
            }

            if (
                ! $this->emailChangeService->hasPendingChange($user)
                && ! $this->emailChangeService->hasPendingPasswordSet($user)
            ) {
                return redirect()
                    ->route('change-email')
                    ->withErrors($exception->errors());
            }

            return back()->withErrors($exception->errors());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Verification email resent. Check your inbox.',
                'resend_cooldown' => 60,
            ]);
        }

        return back()->with('status', 'Verification email resent.');
    }

    public function cancelChangeEmail(Request $request): RedirectResponse
    {
        $this->emailChangeService->cancel($request->user()->fresh());
        $request->session()->forget('email_change_verify_active');

        return redirect()
            ->route('change-email')
            ->with('status', 'Email change request cancelled.');
    }

    public function confirmChangeEmail(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $user = $this->emailChangeService->confirm($id, $token);
        } catch (ValidationException $exception) {
            return $this->redirectAfterEmailChangeConfirmFailure($request, $id, $exception);
        }

        return $this->finishEmailChangeSuccess($request, $user);
    }

    public function showSetPasswordAfterEmailChange(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $user = $this->emailChangeService->validateSetPasswordToken($id, $token);
            $user = $this->emailChangeService->completePendingPasswordSet($user);
        } catch (ValidationException $exception) {
            return $this->redirectAfterEmailChangeConfirmFailure($request, $id, $exception);
        }

        return $this->finishEmailChangeSuccess($request, $user);
    }

    public function updateSetPasswordAfterEmailChange(Request $request, int $id, string $token): RedirectResponse
    {
        return $this->showSetPasswordAfterEmailChange($request, $id, $token);
    }

    public function showChangePassword(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('sign-in')->with('error', 'Please login first.');
        }

        $user = $request->user()->fresh();

        return view('profile::change-password', ['user' => $user])->withHeaders($this->noCacheHeaders());
    }

    public function requestChangePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                'max:64',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        try {
            $this->passwordChangeService->requestChange($request->user(), (string) $validated['password']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('change-password.verify')
            ->with('status', 'Verification link sent to your email address.');
    }

    public function showChangePasswordVerify(Request $request): View|RedirectResponse
    {
        $user = $request->user()->fresh();

        if (! $this->passwordChangeService->hasPendingChange($user)) {
            if ($this->passwordChangeCompletedInThisSession($request)) {
                return redirect()
                    ->route('dashboard')
                    ->with('success', 'Password changed successfully.');
            }

            if ($this->wasPasswordChangeConfirmed($request, $user)) {
                return $this->finishPasswordChangeLogout($request, $user);
            }

            $request->session()->forget('password_change_verify_active');

            return redirect()->route('change-password');
        }

        $request->session()->put('password_change_verify_active', true);

        return view('profile::change-password-verify', [
            'user' => $user,
            'resendCooldown' => $this->passwordChangeService->resendCooldownRemaining($user),
        ])->withHeaders($this->noCacheHeaders());
    }

    public function checkChangePasswordVerifyStatus(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();

        if ($this->passwordChangeService->hasPendingChange($user)) {
            return response()->json([
                'state' => 'pending',
                'resend_cooldown' => $this->passwordChangeService->resendCooldownRemaining($user),
            ]);
        }

        if ($this->passwordChangeCompletedInThisSession($request)) {
            $request->session()->forget('password_change_verify_active');

            return response()->json([
                'state' => 'confirmed',
                'redirect' => MailUrl::sameOrigin(route('dashboard')),
                'message' => 'Password changed successfully. Taking you to your dashboard...',
            ]);
        }

        if ($this->wasPasswordChangeConfirmed($request, $user)) {
            $request->session()->forget('password_change_verify_active');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'state' => 'confirmed',
                'redirect' => MailUrl::sameOrigin(route('sign-in')),
                'message' => 'Password changed successfully. Please sign in with your new password.',
            ]);
        }

        return response()->json([
            'state' => 'cancelled',
            'redirect' => MailUrl::sameOrigin(route('change-password')),
            'message' => 'Password change request is no longer active.',
        ]);
    }

    public function resendChangePassword(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $this->passwordChangeService->resend($request->user()->fresh());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'Unable to resend verification email. Please try again.';

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'resend_cooldown' => $this->passwordChangeService->resendCooldownRemaining($request->user()->fresh()),
                ], 422);
            }

            return back()->withErrors($exception->errors());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Verification email resent. Check your inbox.',
                'resend_cooldown' => 60,
            ]);
        }

        return back()->with('status', 'Verification email resent.');
    }

    public function cancelChangePassword(Request $request): RedirectResponse
    {
        $this->passwordChangeService->cancel($request->user()->fresh());
        $request->session()->forget('password_change_verify_active');
        $this->passwordChangeService->forgetRecentlyConfirmed($request->user()->id);

        return redirect()
            ->route('change-password')
            ->with('status', 'Password change request cancelled.');
    }

    public function confirmChangePassword(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $user = $this->passwordChangeService->confirm($id, $token);
        } catch (ValidationException $exception) {
            return $this->redirectAfterPasswordChangeConfirmFailure($request, $id, $exception);
        }

        $authenticatedId = Auth::id();

        if ($authenticatedId !== null && (int) $authenticatedId !== (int) $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('sign-in')
                ->with('success', 'Password changed successfully. Please sign in with your new password.');
        }

        if (! Auth::check()) {
            Auth::login($user);
        }

        $request->session()->regenerate();
        $request->session()->put(self::PASSWORD_CHANGE_CONFIRMED_SESSION_KEY, true);
        $request->session()->forget('password_change_verify_active');

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password changed successfully.');
    }

    public function uploadSupportingDocument(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validationRules = [
            'document_type' => ['required', Rule::in(SupportingDocumentTypes::allowed())],
        ];

        foreach (SupportingDocumentTypes::allowed() as $type) {
            $validationRules[$type.'_front'] = ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'];
            $validationRules[$type.'_back'] = ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'];
        }

        $request->validate($validationRules);

        $documentType = (string) $request->input('document_type');
        $front = $request->file($documentType.'_front');
        $back = $request->file($documentType.'_back');

        if (! $front || ! $back) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload both front and back images of your selected ID.',
                'errors' => ['document' => ['Please upload both front and back images of your selected ID.']],
            ], 422);
        }

        try {
            $result = $this->supportingDocumentsService->upload($user, [
                'front' => $front,
                'back' => $back,
            ], $documentType);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'documents' => $result['documents'],
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Unable to upload supporting document.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('Kabataan supporting document upload failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload supporting document. Please try again.',
            ], 500);
        }
    }

    public function uploadProfilePicture(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'profile_picture' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        try {
            $result = $this->profileImageService->upload($user, $request->file('profile_picture'));

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully.',
                'picture_url' => $result['picture_url'],
                'next_change_available_at' => $result['next_change_available_at'],
                'next_change_display' => $result['next_change_display'],
                'can_change' => $result['can_change'],
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Unable to upload profile picture.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('Kabataan profile picture upload failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture. Please try again.',
            ], 500);
        }
    }

    protected function wasPasswordChangeConfirmed(Request $request, User $user): bool
    {
        return $this->passwordChangeService->wasRecentlyConfirmed($user->id);
    }

    protected function passwordChangeCompletedInThisSession(Request $request): bool
    {
        return (bool) $request->session()->get(self::PASSWORD_CHANGE_CONFIRMED_SESSION_KEY, false);
    }

    protected function redirectAfterPasswordChangeConfirmFailure(
        Request $request,
        int $id,
        ValidationException $exception,
    ): RedirectResponse {
        $message = collect($exception->errors())->flatten()->first()
            ?: 'This password change link is invalid.';

        if (Auth::check() && (int) Auth::id() === $id) {
            $user = $request->user()->fresh();

            if ($this->passwordChangeService->hasPendingChange($user)) {
                return redirect()
                    ->route('change-password.verify')
                    ->with('error', $message);
            }

            return redirect()
                ->route('change-password')
                ->with('error', $message);
        }

        if (Auth::check()) {
            return redirect()
                ->route('dashboard')
                ->with('error', $message);
        }

        return redirect()
            ->route('sign-in')
            ->with('error', $message);
    }

    protected function wasEmailChangeConfirmed(Request $request, User $user): bool
    {
        return $this->emailChangeService->wasRecentlyCompleted($user->id);
    }

    protected function emailChangeCompletedInThisSession(Request $request): bool
    {
        return (bool) $request->session()->get(self::EMAIL_CHANGE_CONFIRMED_SESSION_KEY, false);
    }

    protected function redirectAfterEmailChangeConfirmFailure(
        Request $request,
        int $id,
        ValidationException $exception,
    ): RedirectResponse {
        $message = collect($exception->errors())->flatten()->first()
            ?: 'This email change link is invalid.';

        if (Auth::check() && (int) Auth::id() === $id) {
            $user = $request->user()->fresh();

            if (
                $this->emailChangeCompletedInThisSession($request)
                || $this->emailChangeService->wasRecentlyCompleted($user->id)
            ) {
                return redirect()
                    ->route('dashboard')
                    ->with('success', 'Email changed successfully.');
            }

            if (
                $this->emailChangeService->hasPendingChange($user)
                || $this->emailChangeService->hasPendingPasswordSet($user)
            ) {
                return redirect()
                    ->route('change-email.verify')
                    ->with('error', $message);
            }

            return redirect()
                ->route('change-email')
                ->with('error', $message);
        }

        if (Auth::check()) {
            return redirect()
                ->route('dashboard')
                ->with('error', $message);
        }

        return redirect()
            ->route('sign-in')
            ->with('error', $message);
    }

    protected function finishEmailChangeLogout(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget('email_change_verify_active');

        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('sign-in')
            ->with('success', 'Email changed successfully. Please sign in with your new email.');
    }

    protected function finishEmailChangeSuccess(Request $request, User $user): RedirectResponse
    {
        $authenticatedId = Auth::id();

        if ($authenticatedId !== null && (int) $authenticatedId !== (int) $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('sign-in')
                ->with('success', 'Email changed successfully. Please sign in with your new email.');
        }

        if (! Auth::check()) {
            Auth::login($user);
        }

        $request->session()->regenerate();
        $request->session()->put(self::EMAIL_CHANGE_CONFIRMED_SESSION_KEY, true);
        $request->session()->forget('email_change_verify_active');

        return redirect()
            ->route('dashboard')
            ->with('success', 'Email changed successfully.');
    }

    protected function finishPasswordChangeLogout(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget('password_change_verify_active');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('sign-in')
            ->with('success', 'Password changed successfully. Please sign in with your new password.');
    }

    /**
     * @return array<string, string>
     */
    protected function noCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ];
    }
}
