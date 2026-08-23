<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authentication\Services\AccountActivationRecoveryService;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountActivationController extends Controller
{
    public function __construct(
        protected AccountActivationRecoveryService $accountActivationRecoveryService,
        protected TurnstileService $turnstileService,
    ) {}

    public function showRequestForm(): View
    {
        return view('authentication::verify-account');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        if ($fail = $this->turnstileService->requestFailed($request)) {
            return back()
                ->withInput()
                ->with('verify_account_error', $fail);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $outcome = $this->accountActivationRecoveryService->requestNewLink($request, $email);

        if ($outcome === AccountActivationRecoveryService::OUTCOME_THROTTLED) {
            if ($request->session()->has(AccountActivationRecoveryService::SESSION_EMAIL_KEY)) {
                return redirect()->route('account.activation.sent');
            }

            return back()
                ->withInput()
                ->with('verify_account_error', AccountActivationRecoveryService::THROTTLED_MESSAGE);
        }

        if ($outcome === AccountActivationRecoveryService::OUTCOME_ALREADY_ACTIVE) {
            $request->session()->forget(AccountActivationRecoveryService::SESSION_EMAIL_KEY);

            return redirect()->route('account.activation.already-active');
        }

        if ($outcome === AccountActivationRecoveryService::OUTCOME_INVALID) {
            return back()
                ->withInput()
                ->with('verify_account_error', AccountActivationRecoveryService::INVALID_MESSAGE);
        }

        $request->session()->put(AccountActivationRecoveryService::SESSION_EMAIL_KEY, $email);

        return redirect()->route('account.activation.sent');
    }

    public function showSent(Request $request): View|RedirectResponse
    {
        $email = Str::lower(trim((string) $request->session()->get(AccountActivationRecoveryService::SESSION_EMAIL_KEY, '')));

        if ($email === '') {
            return redirect()->route('account.activation.request');
        }

        return view('authentication::verify-account-sent', [
            'email' => $email,
            'message' => AccountActivationRecoveryService::SENT_MESSAGE,
            'cooldownSeconds' => $this->accountActivationRecoveryService->cooldownSeconds(),
            'cooldownRemaining' => $this->accountActivationRecoveryService->cooldownRemaining($email),
        ]);
    }

    public function showAlreadyActive(): View
    {
        return view('authentication::verify-account-status', [
            'title' => 'Account Already Activated',
            'message' => AccountActivationRecoveryService::ALREADY_ACTIVE_MESSAGE,
            'showVerifyButton' => false,
        ]);
    }
}
