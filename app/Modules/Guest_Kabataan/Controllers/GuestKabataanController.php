<?php

namespace App\Modules\Guest_Kabataan\Controllers;

use App\Modules\Guest_Kabataan\Services\GuestKabataanClaimService;
use App\Modules\Guest_Kabataan\Services\GuestKabataanService;
use App\Services\BarangayZoneService;
use App\Services\KkRegistrationDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GuestKabataanController
{
    private const ALREADY_EMAIL_MESSAGE = 'This KK Profiling already has an email. Sign in using that email instead.';

    public function __construct(
        private GuestKabataanService $guest,
        private GuestKabataanClaimService $claims,
        private BarangayZoneService $zones,
    ) {}

    public function barangays(): View|RedirectResponse
    {
        if ($redirect = $this->redirectSignedInUser()) {
            return $redirect;
        }

        return view('guest_kabataan::guest_kabataan_barangays', [
            'barangays' => $this->guest->barangays(),
            'selected' => $this->guest->sessionBarangay(),
        ]);
    }

    public function selectBarangay(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectSignedInUser()) {
            return $redirect;
        }

        $validated = $request->validate([
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
        ]);

        $this->guest->rememberBarangay((int) $validated['barangay_id']);

        return redirect()->route('guest_kabataan.home');
    }

    public function home(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectSignedInUser()) {
            return $redirect;
        }

        $barangay = $this->guest->sessionBarangay();
        if ($barangay === null) {
            return redirect()->route('guest_kabataan.barangays');
        }

        return view('guest_kabataan::guest_kabataan', [
            'barangay' => $barangay,
            'programs' => $this->guest->openPrograms($barangay),
            'zones' => $this->zones->activeZonesForBarangay((int) $barangay->id),
            'lockStatus' => $this->claims->lockStatus($request),
        ]);
    }

    public function claimLock(Request $request): JsonResponse
    {
        if ($redirect = $this->redirectSignedInUser()) {
            return response()->json(['message' => 'Already signed in.'], 403);
        }

        return response()->json($this->claims->lockStatus($request));
    }

    public function confirmIdentity(Request $request): JsonResponse
    {
        if ($this->redirectSignedInUser()) {
            return response()->json(['message' => 'Already signed in.'], 403);
        }

        $barangay = $this->guest->sessionBarangay();
        if ($barangay === null) {
            return response()->json(['message' => 'Choose a barangay first.'], 422);
        }

        $result = $this->claims->confirmIdentity($request, (int) $barangay->id, $request->all());
        if ($result['status'] === 'not_found') {
            return response()->json([
                'success' => false,
                'not_found' => true,
                'message' => 'Walang nahanap na KK Profiling para sa taong '.now()->year.' na tumutugma sa impormasyong inilagay mo. Ang KK Profiling lamang ngayong taon ang maaaring gamitin. Pakisuri ang mga field, o mag-sign up para sa KK Profiling ngayong taon.',
            ]);
        }

        if ($result['status'] === 'already_account') {
            return response()->json([
                'success' => false,
                'already_account' => true,
                'message' => self::ALREADY_EMAIL_MESSAGE,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function claim(Request $request): JsonResponse
    {
        if ($this->redirectSignedInUser()) {
            return response()->json(['message' => 'Already signed in.'], 403);
        }

        $barangay = $this->guest->sessionBarangay();
        if ($barangay === null) {
            return response()->json(['message' => 'Choose a barangay first.'], 422);
        }

        $result = $this->claims->submitSecurity($request, (int) $barangay->id, $request->all());
        if ($result['status'] === 'wrong') {
            $message = $result['locked']
                ? 'Masyadong maraming maling subok. Maaari kang sumubok muli pagkalipas ng '.$this->clock($result['remaining_seconds']).'.'
                : 'Mali ang mga security questions. Natitirang subok: '.$result['attempts_left'].'.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'locked' => $result['locked'],
                'remaining_seconds' => $result['remaining_seconds'],
                'attempts_left' => $result['attempts_left'],
            ], 422);
        }

        if ($result['status'] === 'already_account') {
            return response()->json([
                'success' => false,
                'already_account' => true,
                'message' => self::ALREADY_EMAIL_MESSAGE,
            ]);
        }

        return response()->json([
            'success' => true,
            'redirect' => route('guest_kabataan.activate'),
        ]);
    }

    public function activate(): View|RedirectResponse
    {
        if ($redirect = $this->redirectSignedInUser()) {
            return $redirect;
        }

        $barangay = $this->guest->sessionBarangay();
        if ($barangay === null) {
            return redirect()->route('guest_kabataan.barangays');
        }

        $registration = $this->claims->claimedRegistration((int) $barangay->id);
        if ($registration === null) {
            return redirect()->route('guest_kabataan.home');
        }

        return view('guest_kabataan::guest_kabataan_activate', [
            'mode' => 'form',
            'barangay' => $barangay,
            'email' => strtolower(trim((string) ($registration->email ?? ''))),
            'cooldown' => 0,
        ]);
    }

    public function activateSent(): View|RedirectResponse
    {
        if ($redirect = $this->redirectSignedInUser()) {
            return $redirect;
        }

        $barangay = $this->guest->sessionBarangay();
        if ($barangay === null) {
            return redirect()->route('guest_kabataan.barangays');
        }

        $registration = $this->claims->claimedRegistration((int) $barangay->id);
        if ($registration === null) {
            return redirect()->route('guest_kabataan.home');
        }

        $wizard = app(KkRegistrationDraftService::class)->resolveWizard();
        $email = strtolower(trim((string) ($registration->email ?? '')));
        $sent = is_array($wizard)
            && $email !== ''
            && strtolower(trim((string) ($wizard['email'] ?? ''))) === $email
            && ! empty($wizard['verification_sent_at']);
        if (! $sent) {
            return redirect()->route('guest_kabataan.activate');
        }

        $remaining = (int) (app(KkRegistrationDraftService::class)->setPasswordEmailCooldown($wizard)['remaining_seconds'] ?? 0);

        return view('guest_kabataan::guest_kabataan_activate', [
            'mode' => 'sent',
            'barangay' => $barangay,
            'email' => $email,
            'cooldown' => $remaining,
        ]);
    }

    public function sendActivation(Request $request): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectSignedInUser()) {
            return $redirect;
        }

        $barangay = $this->guest->sessionBarangay();
        if ($barangay === null) {
            return response()->json(['message' => 'Choose a barangay first.'], 422);
        }

        $registration = $this->claims->claimedRegistration((int) $barangay->id);
        if ($registration === null) {
            return response()->json(['message' => 'Confirm your KK Profiling again before adding an email.'], 422);
        }

        $this->claims->requireTurnstile($request);
        $this->claims->sendActivationEmail($registration, (string) $request->input('email', ''));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'email' => strtolower(trim((string) $request->input('email', ''))),
                'message' => 'Set password link sent. Please check your inbox.',
                'redirect' => route('guest_kabataan.activate.sent'),
            ]);
        }

        return redirect()->route('guest_kabataan.activate.sent');
    }

    public function resendActivation(Request $request): JsonResponse
    {
        if ($this->redirectSignedInUser()) {
            return response()->json(['message' => 'Already signed in.'], 403);
        }

        $barangay = $this->guest->sessionBarangay();
        $registration = $barangay ? $this->claims->claimedRegistration((int) $barangay->id) : null;
        if ($registration === null) {
            return response()->json(['message' => 'Confirm your KK Profiling again before resending the email.'], 422);
        }

        try {
            $this->claims->resendActivationEmail($registration);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Unable to resend the email.';
            $remaining = 0;
            if (preg_match('/\b(\d+)\s+second/', (string) $message, $matches) === 1) {
                $remaining = (int) $matches[1];
            }

            return response()->json([
                'message' => $message,
                'errors' => $exception->errors(),
                'resend_cooldown_seconds' => $remaining,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Set password link sent. Please check your inbox.',
            'resend_cooldown_seconds' => 60,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->guest->forget();
        $this->claims->forgetClaim();

        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        $request->session()->regenerateToken();

        return redirect()->route('sign-in');
    }

    private function clock(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);
        $remain = max(0, $seconds) % 60;

        return $minutes.':'.str_pad((string) $remain, 2, '0', STR_PAD_LEFT);
    }

    private function redirectSignedInUser(): ?RedirectResponse
    {
        if (! auth()->check()) {
            return null;
        }

        return redirect()->route('dashboard');
    }
}
