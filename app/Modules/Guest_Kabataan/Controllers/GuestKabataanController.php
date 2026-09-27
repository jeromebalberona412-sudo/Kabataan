<?php

namespace App\Modules\Guest_Kabataan\Controllers;

use App\Modules\Guest_Kabataan\Services\GuestKabataanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestKabataanController
{
    public function __construct(private GuestKabataanService $guest) {}

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

    public function home(): View|RedirectResponse
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
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->guest->forget();

        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        $request->session()->regenerateToken();

        return redirect()->route('sign-in');
    }

    private function redirectSignedInUser(): ?RedirectResponse
    {
        if (! auth()->check()) {
            return null;
        }

        return redirect()->route('dashboard');
    }
}
