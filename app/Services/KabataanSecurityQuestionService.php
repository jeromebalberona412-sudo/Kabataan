<?php

namespace App\Services;

use App\Models\KabataanRegistration;
use App\Models\KabataanSecurityQuestion;
use App\Support\KabataanSecurityQuestionCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class KabataanSecurityQuestionService
{
    /**
     * Validate exactly 3 answers and return hashed rows. Raw answers are not returned.
     *
     * @return list<array{question_number: int, question_text: string, selected_choice: string, answer_hash: string}>
     */
    public function prepare(mixed $input): array
    {
        if (! is_array($input)) {
            throw ValidationException::withMessages([
                'security_questions' => ['Pumili at sagutan ang eksaktong 3 tanong.'],
            ]);
        }

        $rows = array_values($input);
        if (count($rows) !== KabataanSecurityQuestionCatalog::REQUIRED_COUNT) {
            throw ValidationException::withMessages([
                'security_questions' => ['Pumili at sagutan ang eksaktong 3 tanong.'],
            ]);
        }

        $prepared = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'security_questions' => ['Pumili at sagutan ang eksaktong 3 tanong.'],
                ]);
            }

            $number = (int) ($row['question_number'] ?? 0);
            if (isset($seen[$number])) {
                throw ValidationException::withMessages([
                    'security_questions' => ['Pumili ng 3 magkakaibang tanong.'],
                ]);
            }
            $seen[$number] = true;

            $question = KabataanSecurityQuestionCatalog::find($number);
            if ($question === null) {
                throw ValidationException::withMessages([
                    'security_questions' => ['May hindi tanggap na tanong. Pumili muli ng 3 tanong.'],
                ]);
            }

            $choice = trim((string) ($row['selected_choice'] ?? ''));
            $custom = trim((string) ($row['custom_answer'] ?? ''));
            $isCustom = $question['kind'] === 'own' || strcasecmp($choice, KabataanSecurityQuestionCatalog::IBA_PA) === 0;

            if ($isCustom) {
                $normalized = $question['kind'] === 'number'
                    ? $this->normalizeNumber($custom)
                    : $this->normalizeText($custom);
                $marker = 'iba_pa';
            } else {
                if (! in_array($choice, $question['choices'], true)) {
                    throw ValidationException::withMessages([
                        'security_questions' => ['Pumili ng sagot sa bawat napiling tanong.'],
                    ]);
                }
                $normalized = $this->normalizeListed($choice);
                $marker = 'listed';
            }

            $prepared[] = [
                'question_number' => $number,
                'question_text' => $question['text'],
                'selected_choice' => $marker,
                'answer_hash' => Hash::make($normalized),
            ];
        }

        return $prepared;
    }

    /**
     * @param  list<array{question_number: int, question_text: string, selected_choice: string, answer_hash: string}>  $prepared
     */
    public function store(KabataanRegistration $registration, array $prepared): void
    {
        foreach ($prepared as $row) {
            KabataanSecurityQuestion::query()->updateOrCreate(
                [
                    'kabataan_registration_id' => $registration->id,
                    'question_number' => $row['question_number'],
                ],
                [
                    'user_id' => $registration->user_id,
                    'question_text' => $row['question_text'],
                    'selected_choice' => $row['selected_choice'],
                    'custom_answer' => null,
                    'answer_hash' => $row['answer_hash'],
                ]
            );
        }
    }

    public function matches(KabataanSecurityQuestion $row, string $attempt): bool
    {
        $question = KabataanSecurityQuestionCatalog::find((int) $row->question_number);
        if ($question === null || $row->answer_hash === null || $row->answer_hash === '') {
            return false;
        }

        try {
            if ($row->selected_choice === 'iba_pa') {
                $normalized = $question['kind'] === 'number'
                    ? $this->normalizeNumber($attempt)
                    : $this->normalizeText($attempt);
            } else {
                $normalized = $this->normalizeListed($attempt);
            }
        } catch (ValidationException) {
            return false;
        }

        return Hash::check($normalized, $row->answer_hash);
    }

    private function normalizeListed(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw ValidationException::withMessages([
                'security_questions' => ['Pumili ng sagot sa bawat napiling tanong.'],
            ]);
        }

        return mb_strtolower($value, 'UTF-8');
    }

    private function normalizeNumber(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^\d{1,2}$/', $value)) {
            throw ValidationException::withMessages([
                'security_questions' => [KabataanSecurityQuestionCatalog::NUMBER_MESSAGE],
            ]);
        }

        $number = (int) $value;
        if ($number > 99) {
            throw ValidationException::withMessages([
                'security_questions' => [KabataanSecurityQuestionCatalog::NUMBER_MESSAGE],
            ]);
        }

        return (string) $number;
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^\p{L}{1,15}$/u', $value)) {
            throw ValidationException::withMessages([
                'security_questions' => [KabataanSecurityQuestionCatalog::TEXT_MESSAGE],
            ]);
        }

        return mb_strtolower($value, 'UTF-8');
    }
}
