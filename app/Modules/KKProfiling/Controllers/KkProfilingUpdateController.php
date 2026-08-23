<?php

namespace App\Modules\KKProfiling\Controllers;

use App\Http\Controllers\Controller;
use App\Models\KabataanRegistration;
use App\Services\BarangayZoneService;
use App\Services\KabataanProfilingHistoryService;
use App\Services\KkProfilingScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KkProfilingUpdateController extends Controller
{
    public function __construct(
        private readonly KkProfilingScheduleService $scheduleService,
        private readonly KabataanProfilingHistoryService $profilingHistoryService,
        private readonly BarangayZoneService $barangayZoneService,
    ) {}

    public function show(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('sign-in');
        }

        $registration = KabataanRegistration::with('barangay')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $registration || ! $this->scheduleService->requiresProfilingUpdate($registration)) {
            $request->session()->put('kk_profiling_update_required', false);

            return redirect()->route('dashboard');
        }

        $request->session()->put('kk_profiling_update_required', true);

        $formData = is_array($registration->form_data) ? $registration->form_data : [];
        $respondentNumber = $formData['respondent_number'] ?? $registration->respondent_number;
        $barangayName = $registration->barangay?->name ?? 'Santa Cruz';
        $purok = $formData['purok_zone'] ?? '';
        if (is_array($purok)) {
            $purok = $purok[0] ?? '';
        }

        return view('kkprofiling::kk-profiling-update', [
            'user' => $user,
            'kkProfilingUpdateRequired' => true,
            'kkProfilingTargetYear' => $this->scheduleService->targetProfilingYearForRegistration($registration),
            'kkProfilingFormData' => $this->profilingHistoryService->formDataForUpdate($registration),
            'kkProfilingOriginalEmail' => $registration->email,
            'kkUpdateBarangay' => $barangayName,
            'kkRespondentNumber' => $respondentNumber ?? '',
            'kkRespondentDisplay' => KKProfilingController::formatRespondentDisplay($respondentNumber),
            'kkBarangayLogoUrl' => KKProfilingController::getBarangayLogoUrl($registration->barangay_id),
            'kkBarangayZones' => $this->barangayZoneService->activeZonesForBarangay((int) $registration->barangay_id),
            'kkSelectedPurokZone' => $purok,
            'kkSelectedFacebookProfileUrl' => $formData['facebook_profile_url'] ?? ($formData['facebook'] ?? ''),
        ]);
    }
}
