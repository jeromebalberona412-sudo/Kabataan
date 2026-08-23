<?php

namespace App\Modules\Profile\Services;

use App\Models\Barangay;
use App\Models\KabataanRegistration;
use App\Models\KkSurveyResponse;
use App\Models\User;
use App\Modules\KKProfiling\Controllers\KKProfilingController;
use App\Services\CloudinaryService;
use App\Support\SupportingDocumentTypes;

class ProfileService
{
    /**
     * @return array{
     *     fullName: string,
     *     barangayName: string,
     *     municipality: string,
     *     province: string,
     *     region: string,
     *     barangayLogoUrl: ?string,
     *     registration: ?KabataanRegistration
     * }
     */
    public function getDisplayData(User $user): array
    {
        $registration = KabataanRegistration::with('barangay')
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNotNull('submitted_at')
                    ->orWhereNotNull('form_data');
            })
            ->latest('id')
            ->first();

        $formData = $registration?->form_data ?? [];
        $barangay = $registration?->barangay;

        if ($barangay === null && $user->barangay_id) {
            $barangay = Barangay::query()->find($user->barangay_id);
        }

        $barangayName = $barangay?->name
            ?? ($formData['barangay'] ?? null)
            ?? 'Santa Cruz';

        $barangayId = $registration?->barangay_id ?? $user->barangay_id;

        return [
            'fullName' => mb_strtoupper($this->buildFullName($registration, $user)),
            'barangayName' => $barangayName,
            'municipality' => $barangay?->municipality ?? 'Santa Cruz',
            'province' => $barangay?->province ?? 'Laguna',
            'region' => $barangay?->region ?? 'Region IV-A (CALABARZON)',
            'barangayLogoUrl' => KKProfilingController::getBarangayLogoUrl($barangayId),
            'registration' => $registration,
            'supportingDocuments' => $this->resolveSupportingDocuments($registration),
        ];
    }

    /**
     * @return array<int, array{type: string, label: string, url: string, display_name: string}>
     */
    public function resolveSupportingDocuments(?KabataanRegistration $registration): array
    {
        if ($registration === null) {
            return [];
        }

        $surveyResponse = KkSurveyResponse::query()
            ->where('kabataan_registration_id', $registration->id)
            ->first();

        $documents = $surveyResponse?->supporting_documents;

        if (! is_array($documents) || $documents === []) {
            $documents = $registration->form_data['supporting_documents'] ?? [];
        }

        if (! is_array($documents) || $documents === []) {
            return [];
        }

        $cloudinary = app(CloudinaryService::class);

        return collect($documents)
            ->flatMap(function (array $document) use ($cloudinary) {
                $type = (string) ($document['type'] ?? 'document');
                $baseLabel = SupportingDocumentTypes::label($type);

                if (isset($document['sides']) && is_array($document['sides'])) {
                    return collect($document['sides'])->map(function (array $sideDoc, string $side) use ($type, $baseLabel, $cloudinary) {
                        $url = $sideDoc['url'] ?? null;
                        if (! $url) {
                            return null;
                        }

                        if (($sideDoc['storage'] ?? '') === 'cloudinary' && $cloudinary->isConfigured()) {
                            $url = $cloudinary->normalizeUrl($url) ?? $url;
                        }

                        return [
                            'type' => $type,
                            'side' => $side,
                            'label' => $baseLabel.' ('.ucfirst($side).')',
                            'url' => $url,
                            'display_name' => (string) ($sideDoc['display_name'] ?? $sideDoc['original_name'] ?? ucfirst($side)),
                        ];
                    });
                }

                $url = $document['url'] ?? null;
                if (! $url) {
                    return collect([null]);
                }

                if (($document['storage'] ?? '') === 'cloudinary' && $cloudinary->isConfigured()) {
                    $url = $cloudinary->normalizeUrl($url) ?? $url;
                }

                return collect([[
                    'type' => $type,
                    'side' => null,
                    'label' => $baseLabel,
                    'url' => $url,
                    'display_name' => (string) ($document['display_name'] ?? $document['original_name'] ?? 'Document'),
                ]]);
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function buildFullName(?KabataanRegistration $registration, User $user): string
    {
        if ($registration !== null) {
            $formData = $registration->form_data ?? [];

            $last = trim((string) ($formData['last_name'] ?? $registration->last_name ?? ''));
            $first = trim((string) ($formData['first_name'] ?? $registration->first_name ?? ''));
            $middle = trim((string) ($formData['middle_name'] ?? $registration->middle_name ?? ''));
            $suffix = trim((string) ($formData['suffix'] ?? $registration->suffix ?? ''));
            if (strcasecmp($suffix, 'None') === 0) {
                $suffix = '';
            }

            $parts = array_filter([$last, $first, $middle, $suffix], fn ($part) => $part !== '');

            if ($parts !== []) {
                if ($last !== '' && $first !== '') {
                    $name = $last.', '.$first;
                    if ($middle !== '') {
                        $name .= ' '.$middle;
                    }
                    if ($suffix !== '') {
                        $name .= ' '.$suffix;
                    }

                    return $name;
                }

                return implode(' ', $parts);
            }
        }

        return trim((string) ($user->name ?? ''));
    }
}
