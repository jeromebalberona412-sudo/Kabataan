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

        if ($this->emailChangeService->hasPendingChange($user) || $this->emailChangeService->hasPendingPasswordSet($user)) {
            return redirect()->route('change-email.verify');
        }

        return view('profile::change-email', ['user' => $user])->withHeaders($this->noCacheHeaders());
    }

    public function requestChangeEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_email' => ['required', 'email', 'max:255'],
            'new_email' => ['required', 'email', 'max:255', 'different:current_email'],
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

        if (! $this->emailChangeService->hasPendingChange($user) && ! $this->emailChangeService->hasPendingPasswordSet($user)) {
            return redirect()->route('change-email');
        }

        return view('profile::change-email-verify', [
            'user' => $user,
            'resendCooldown' => $this->emailChangeService->resendCooldownRemaining($user),
            'awaitingPassword' => $this->emailChangeService->hasPendingPasswordSet($user),
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
            return response()->json([
                'state' => 'awaiting_password',
                'pending_email' => $user->pending_email,
                'message' => 'Email verified. Set your new password on the other tab to finish.',
            ]);
        }

        if ($this->emailChangeService->wasRecentlyCompleted($user->id)) {
            $this->emailChangeService->forgetRecentlyCompleted($user->id);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'state' => 'completed',
                'redirect' => route('sign-in'),
                'message' => 'Email and password updated. Please sign in with your new credentials.',
            ]);
        }

        return response()->json([
            'state' => 'cancelled',
            'redirect' => route('change-email'),
            'message' => 'Email change request is no longer active.',
        ]);
    }

    public function resendChangeEmail(Request $request): RedirectResponse
    {
        try {
            $this->emailChangeService->resend($request->user()->fresh());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Verification email resent.');
    }

    public function cancelChangeEmail(Request $request): RedirectResponse
    {
        $this->emailChangeService->cancel($request->user()->fresh());

        return redirect()
            ->route('change-email')
            ->with('status', 'Email change request cancelled.');
    }

    public function confirmChangeEmail(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $result = $this->emailChangeService->confirm($id, $token);
        } catch (ValidationException $exception) {
            return redirect()->route('sign-in')->withErrors($exception->errors());
        }

        return redirect()
            ->route('change-email.set-password', [
                'id' => $result['user']->id,
                'token' => $result['set_password_token'],
            ])
            ->with('status', 'Email verified. Set a new password to complete the change.');
    }

    public function showSetPasswordAfterEmailChange(Request $request, int $id, string $token): View|RedirectResponse
    {
        try {
            $user = $this->emailChangeService->validateSetPasswordToken($id, $token);
        } catch (ValidationException $exception) {
            return redirect()->route('sign-in')->withErrors($exception->errors());
        }

        return view('profile::set-password', [
            'user' => $user,
            'token' => $token,
        ])->withHeaders($this->noCacheHeaders());
    }

    public function updateSetPasswordAfterEmailChange(Request $request, int $id, string $token): RedirectResponse
    {
        try {
            $user = $this->emailChangeService->validateSetPasswordToken($id, $token);
        } catch (ValidationException $exception) {
            return redirect()->route('sign-in')->withErrors($exception->errors());
        }

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
            $this->emailChangeService->completePasswordSet($user, (string) $validated['password']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $this->emailChangeService->markRecentlyCompleted($user->id);

        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('sign-in')
            ->with('status', 'Email and password updated. Sign in with your new credentials.');
    }

    public function showChangePassword(Request $request): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('sign-in')->with('error', 'Please login first.');
        }

        $user = $request->user()->fresh();

        if ($this->passwordChangeService->hasPendingChange($user)) {
            return redirect()->route('change-password.verify');
        }

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

        if ($this->wasPasswordChangeConfirmed($request, $user)) {
            $request->session()->forget('password_change_verify_active');
            $this->passwordChangeService->forgetRecentlyConfirmed($user->id);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'state' => 'confirmed',
                'redirect' => route('sign-in'),
                'message' => 'Password changed successfully. Please sign in with your new password.',
            ]);
        }

        return response()->json([
            'state' => 'cancelled',
            'redirect' => route('change-password'),
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
            return redirect()->route('sign-in')->withErrors($exception->errors());
        }

        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('sign-in')
            ->with('status', 'Password changed successfully for '.$user->email.'. Please sign in with your new password.');
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
        return $this->passwordChangeService->wasRecentlyConfirmed($user->id)
            || (bool) $request->session()->get('password_change_verify_active', false);
    }

    protected function finishPasswordChangeLogout(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget('password_change_verify_active');
        $this->passwordChangeService->forgetRecentlyConfirmed($user->id);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('sign-in')
            ->with('status', 'Password changed successfully. Please sign in with your new password.');
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
