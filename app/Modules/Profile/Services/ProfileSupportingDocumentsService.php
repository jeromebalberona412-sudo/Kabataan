<?php

namespace App\Modules\Profile\Services;

use App\Models\KabataanRegistration;
use App\Models\KkSurveyResponse;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Services\KkSurveyResponseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\SupportingDocumentTypes;

class ProfileSupportingDocumentsService
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    public function __construct(
        private readonly CloudinaryService $cloudinary,
        private readonly ProfileService $profileService,
    ) {}

    public function findRegistration(User $user): ?KabataanRegistration
    {
        return KabataanRegistration::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNotNull('submitted_at')
                    ->orWhereNotNull('form_data');
            })
            ->latest('id')
            ->first();
    }

    /**
     * @param  array{front: UploadedFile, back: UploadedFile}  $files
     * @return array{
     *     documents: array<int, array{type: string, label: string, url: string, display_name: string}>,
     *     message: string
     * }
     */
    public function upload(User $user, array $files, string $documentType): array
    {
        $registration = $this->findRegistration($user);

        if ($registration === null) {
            throw ValidationException::withMessages([
                'document' => ['Complete KK Profiling registration before uploading supporting documents.'],
            ]);
        }

        if (! in_array($documentType, SupportingDocumentTypes::allowed(), true)) {
            throw ValidationException::withMessages([
                'document_type' => ['The selected document type is invalid.'],
            ]);
        }

        $front = $files['front'] ?? null;
        $back = $files['back'] ?? null;

        if (! $front instanceof UploadedFile || ! $back instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'document' => ['Please upload both front and back images of your selected ID.'],
            ]);
        }

        $this->assertValidFile($front);
        $this->assertValidFile($back);

        if ($this->findAnyStoredDocument($registration)) {
            throw ValidationException::withMessages([
                'document' => ['Supporting documents have already been uploaded and cannot be replaced.'],
            ]);
        }

        $emailSlug = Str::slug(strtolower($registration->email), '_') ?: 'user_'.$user->id;
        $timestamp = now()->format('YmdHis');
        $sides = [];

        foreach (['front' => $front, 'back' => $back] as $side => $file) {
            $originalName = $file->getClientOriginalName();
            $displayName = pathinfo($originalName, PATHINFO_FILENAME);
            $publicId = $emailSlug.'_'.Str::slug($documentType, '_').'_'.$side.'_'.$timestamp;
            $uploaded = $this->uploadToCloudOrLocal($file, $publicId, $displayName);

            $sides[$side] = [
                'path' => $uploaded['public_id'],
                'url' => $uploaded['url'],
                'public_id' => $uploaded['public_id'],
                'cloudinary_version' => $uploaded['version'] ?? null,
                'original_name' => $originalName,
                'display_name' => $displayName,
                'storage' => $uploaded['storage'],
            ];
        }

        $document = [
            'type' => $documentType,
            'sides' => $sides,
        ];

        DB::transaction(function () use ($registration, $document) {
            $formData = $registration->form_data ?? [];
            $formData['supporting_documents'] = [$document];

            $registration->update(['form_data' => $formData]);

            $surveyResponse = KkSurveyResponse::query()
                ->where('kabataan_registration_id', $registration->id)
                ->first();

            if ($surveyResponse) {
                $surveyResponse->update(['supporting_documents' => [$document]]);
            } else {
                (new KkSurveyResponseService)->syncFromRegistration($registration->fresh(), 'pending');
            }
        });

        $freshRegistration = $registration->fresh();
        $documents = $this->profileService->resolveSupportingDocuments($freshRegistration);

        Log::info('Kabataan supporting document uploaded from profile', [
            'user_id' => $user->id,
            'registration_id' => $registration->id,
            'document_type' => $documentType,
        ]);

        return [
            'documents' => $documents,
            'message' => 'Supporting documents uploaded successfully.',
        ];
    }

    /**
     * @return array{public_id: string, url: string, version: int|null, storage: string}
     */
    private function uploadToCloudOrLocal(UploadedFile $file, string $publicId, string $displayName): array
    {
        if ($this->cloudinary->isConfigured()) {
            try {
                $result = $this->cloudinary->uploadSupportingDocument($file, $publicId, $displayName);

                return [
                    'public_id' => $result['public_id'],
                    'url' => $result['url'],
                    'version' => $result['version'],
                    'storage' => 'cloudinary',
                ];
            } catch (\Throwable $exception) {
                Log::warning('Cloudinary supporting document upload failed, falling back to local storage', [
                    'public_id' => $publicId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $extension = strtolower((string) ($file->guessExtension() ?: 'jpg'));
        $filename = Str::slug($publicId, '_').'.'.$extension;
        $directory = 'kabataan_documents/profile';
        $path = $directory.'/'.$filename;

        Storage::disk('public')->putFileAs($directory, $file, $filename);

        return [
            'public_id' => $path,
            'url' => Storage::disk('public')->url($path),
            'version' => null,
            'storage' => 'local',
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function deleteStoredDocument(array $document): void
    {
        if (isset($document['sides']) && is_array($document['sides'])) {
            foreach ($document['sides'] as $sideDoc) {
                if (is_array($sideDoc)) {
                    $this->deleteStoredDocument($sideDoc);
                }
            }

            return;
        }

        $publicId = (string) ($document['public_id'] ?? $document['path'] ?? '');

        if ($publicId === '') {
            return;
        }

        if (($document['storage'] ?? '') === 'local' || str_starts_with($publicId, 'kabataan_documents/')) {
            Storage::disk('public')->delete($publicId);

            return;
        }

        if (! $this->cloudinary->isConfigured()) {
            return;
        }

        try {
            $this->cloudinary->delete($publicId);
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete previous supporting document from Cloudinary', [
                'public_id' => $publicId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStoredDocument(KabataanRegistration $registration, string $documentType): ?array
    {
        $surveyResponse = KkSurveyResponse::query()
            ->where('kabataan_registration_id', $registration->id)
            ->first();

        $documents = $surveyResponse?->supporting_documents;

        if (! is_array($documents) || $documents === []) {
            $documents = $registration->form_data['supporting_documents'] ?? [];
        }

        if (! is_array($documents)) {
            return null;
        }

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            if (($document['type'] ?? '') === $documentType) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAnyStoredDocument(KabataanRegistration $registration): ?array
    {
        $surveyResponse = KkSurveyResponse::query()
            ->where('kabataan_registration_id', $registration->id)
            ->first();

        $documents = $surveyResponse?->supporting_documents;

        if (! is_array($documents) || $documents === []) {
            $documents = $registration->form_data['supporting_documents'] ?? [];
        }

        if (! is_array($documents)) {
            return null;
        }

        $first = $documents[0] ?? null;

        return is_array($first) ? $first : null;
    }

    private function assertValidFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'document' => ['Please select a valid image file.'],
            ]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'document' => ['Supporting document must be 10MB or smaller.'],
            ]);
        }

        $mime = strtolower((string) $file->getMimeType());

        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'document' => ['Only JPG and PNG images are allowed.'],
            ]);
        }
    }
}
