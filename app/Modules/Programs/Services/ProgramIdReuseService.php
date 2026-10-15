<?php

namespace App\Modules\Programs\Services;

use App\Models\KabataanRegistration;
use App\Models\KkSurveyResponse;
use App\Models\ScheduleProgram;
use App\Models\User;
use App\Support\MailUrl;
use App\Support\SupportingDocumentTypes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProgramIdReuseService
{
    public const SOURCE = 'kk_profiling';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function payloadForProgram(User $user, ScheduleProgram $program): array
    {
        $stored = $this->ownedSupportingDocument($user);
        if ($stored === null) {
            return [];
        }

        $documentType = SupportingDocumentTypes::normalize($stored['type'] ?? null);
        if ($documentType === null || ! $this->documentHasViewableImage($stored)) {
            return [];
        }

        $payload = [];

        foreach ($program->custom_questions ?? [] as $question) {
            if (! is_array($question)) {
                continue;
            }

            $accepted = SupportingDocumentTypes::acceptedIdTypesForQuestion($question);
            if ($accepted === [] || ! in_array($documentType, $accepted, true)) {
                continue;
            }

            $questionId = (string) ($question['id'] ?? '');
            if ($questionId === '') {
                continue;
            }

            $label = SupportingDocumentTypes::label($documentType);

            $payload[$questionId] = [
                'question_id' => $questionId,
                'document_type' => $documentType,
                'document_type_label' => $label,
                'question' => sprintf(
                    'You previously uploaded a %s in your KK Profiling Supporting Documents. Would you like to use this ID for your current program application?',
                    $label
                ),
                'sides' => $this->previewSides($stored),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>|null  $answer
     * @return array<string, mixed>
     */
    public function finalizeReuse(User $user, array $question, ?array $answer): array
    {
        if (! $this->answerRequestsReuse($answer)) {
            throw ValidationException::withMessages([
                'answers.'.($question['id'] ?? 'document') => ['Please choose whether to reuse your existing ID or upload a new ID.'],
            ]);
        }

        $stored = $this->ownedSupportingDocument($user);
        if ($stored === null || ! $this->documentHasViewableImage($stored)) {
            throw ValidationException::withMessages([
                'answers.'.($question['id'] ?? 'document') => ['Your previously uploaded ID is no longer available. Please upload a new ID.'],
            ]);
        }

        $documentType = SupportingDocumentTypes::normalize($stored['type'] ?? null);
        $accepted = SupportingDocumentTypes::acceptedIdTypesForQuestion($question);

        if ($documentType === null || $accepted === [] || ! in_array($documentType, $accepted, true)) {
            throw ValidationException::withMessages([
                'answers.'.($question['id'] ?? 'document') => ['The previously uploaded ID type does not match this program requirement.'],
            ]);
        }

        $label = SupportingDocumentTypes::label($documentType);
        $front = $this->sideMeta($stored, SupportingDocumentTypes::SIDE_FRONT) ?? $this->firstSideMeta($stored);

        return [
            'question_id' => (string) ($question['id'] ?? ''),
            'question_label' => $question['label'] ?? $label,
            'source' => self::SOURCE,
            'reused' => true,
            'document_type' => $documentType,
            'original_name' => $label,
            'mime' => (string) ($front['mime'] ?? 'image/jpeg'),
            'size' => 0,
            'uploaded_at' => now()->toIso8601String(),
        ];
    }

    public function isReuseMeta(mixed $meta): bool
    {
        return is_array($meta)
            && ! empty($meta['reused'])
            && ($meta['source'] ?? '') === self::SOURCE;
    }

    public function answerRequestsReuse(mixed $answer): bool
    {
        if (! is_array($answer)) {
            return false;
        }

        if (! empty($answer['reuse']) || ! empty($answer['reused'])) {
            return true;
        }

        return ($answer['source'] ?? '') === self::SOURCE;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function enrichReuseMeta(User $user, array $meta, int $scheduleProgramId, string $questionId): array
    {
        $stored = $this->ownedSupportingDocument($user);
        $documentType = SupportingDocumentTypes::normalize($meta['document_type'] ?? $stored['type'] ?? null)
            ?? (string) ($meta['document_type'] ?? 'document');
        $label = SupportingDocumentTypes::label($documentType);

        $meta['question_id'] = $questionId;
        $meta['original_name'] = $meta['original_name'] ?? $label;
        $meta['document_type_label'] = $label;
        $meta['status'] = 'reused';
        $meta['preview_url'] = MailUrl::sameOrigin(route('kabataan.programs.documents.show', [
            'scheduleProgramId' => $scheduleProgramId,
            'questionId' => $questionId,
        ]));
        $meta['download_url'] = MailUrl::sameOrigin(route('kabataan.programs.documents.show', [
            'scheduleProgramId' => $scheduleProgramId,
            'questionId' => $questionId,
            'download' => 1,
        ]));
        $meta['sides'] = $stored !== null ? $this->previewSides($stored) : [];

        return $meta;
    }

    public function streamOwnedId(User $user, string $side = SupportingDocumentTypes::SIDE_FRONT, bool $download = false): Response
    {
        $stored = $this->ownedSupportingDocument($user);
        if ($stored === null) {
            abort(404, 'Supporting document not found.');
        }

        return $this->streamStoredDocument($stored, $side, $download);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function streamReuseMeta(User $user, array $meta, string $side = SupportingDocumentTypes::SIDE_FRONT, bool $download = false): Response
    {
        $stored = $this->ownedSupportingDocument($user);
        if ($stored === null) {
            abort(404, 'Supporting document not found.');
        }

        $storedType = SupportingDocumentTypes::normalize($stored['type'] ?? null);
        $metaType = SupportingDocumentTypes::normalize($meta['document_type'] ?? null);

        if ($metaType !== null && $storedType !== null && $metaType !== $storedType) {
            abort(404, 'Supporting document not found.');
        }

        return $this->streamStoredDocument($stored, $side, $download);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ownedSupportingDocument(User $user): ?array
    {
        $registration = KabataanRegistration::query()
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->first();

        if ($registration === null) {
            return null;
        }

        $survey = KkSurveyResponse::query()
            ->where('kabataan_registration_id', $registration->id)
            ->first();

        $documents = $survey?->supporting_documents;
        if (! is_array($documents) || $documents === []) {
            $documents = $registration->form_data['supporting_documents'] ?? [];
        }

        if (! is_array($documents)) {
            return null;
        }

        foreach ($documents as $document) {
            if (is_array($document) && SupportingDocumentTypes::normalize($document['type'] ?? null) !== null) {
                return $document;
            }
        }

        $first = $documents[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function documentHasViewableImage(array $document): bool
    {
        if ($this->sideMeta($document, SupportingDocumentTypes::SIDE_FRONT) !== null) {
            return true;
        }

        if ($this->sideMeta($document, SupportingDocumentTypes::SIDE_BACK) !== null) {
            return true;
        }

        return $this->firstSideMeta($document) !== null;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{side: string, label: string, preview_url: string}>
     */
    private function previewSides(array $document): array
    {
        $sides = [];

        foreach (SupportingDocumentTypes::sides() as $side) {
            if ($this->sideMeta($document, $side) === null) {
                continue;
            }

            $sides[] = [
                'side' => $side,
                'label' => ucfirst($side),
                'preview_url' => MailUrl::sameOrigin(route('kabataan.programs.kk-id.show', [
                    'side' => $side,
                ])),
            ];
        }

        if ($sides === [] && $this->firstSideMeta($document) !== null) {
            $sides[] = [
                'side' => SupportingDocumentTypes::SIDE_FRONT,
                'label' => 'Front',
                'preview_url' => MailUrl::sameOrigin(route('kabataan.programs.kk-id.show', [
                    'side' => SupportingDocumentTypes::SIDE_FRONT,
                ])),
            ];
        }

        return $sides;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>|null
     */
    private function sideMeta(array $document, string $side): ?array
    {
        $sides = is_array($document['sides'] ?? null) ? $document['sides'] : [];
        $meta = is_array($sides[$side] ?? null) ? $sides[$side] : null;

        if ($meta !== null) {
            return $meta;
        }

        if ($side === SupportingDocumentTypes::SIDE_FRONT && $sides === [] && (! empty($document['path']) || ! empty($document['url']))) {
            return $document;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>|null
     */
    private function firstSideMeta(array $document): ?array
    {
        foreach (SupportingDocumentTypes::sides() as $side) {
            $meta = $this->sideMeta($document, $side);
            if ($meta !== null) {
                return $meta;
            }
        }

        return (! empty($document['path']) || ! empty($document['url'])) ? $document : null;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function streamStoredDocument(array $document, string $side, bool $download): Response
    {
        if (! in_array($side, SupportingDocumentTypes::sides(), true)) {
            $side = SupportingDocumentTypes::SIDE_FRONT;
        }

        $meta = $this->sideMeta($document, $side) ?? $this->firstSideMeta($document);
        if ($meta === null) {
            abort(404, 'Document side not found.');
        }

        if (($meta['storage'] ?? '') === 'cloudinary' && ! empty($meta['url'])) {
            return redirect()->away((string) $meta['url']);
        }

        if (! empty($meta['url']) && str_starts_with((string) $meta['url'], 'http')) {
            return redirect()->away((string) $meta['url']);
        }

        $path = (string) ($meta['path'] ?? $meta['public_id'] ?? '');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Document file not found.');
        }

        $originalName = (string) ($meta['original_name'] ?? $meta['display_name'] ?? basename($path));
        $absolutePath = Storage::disk('public')->path($path);
        $mime = (string) ($meta['mime'] ?? mime_content_type($absolutePath) ?: 'image/jpeg');
        $disposition = $download ? 'attachment' : 'inline';

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($originalName).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
