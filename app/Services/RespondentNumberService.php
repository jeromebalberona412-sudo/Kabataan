<?php

namespace App\Services;

use App\Models\KabataanRegistration;
use App\Models\KkSurveyResponse;
use Illuminate\Support\Facades\DB;

class RespondentNumberService
{
    public function assignToRegistration(KabataanRegistration $registration): string
    {
        if ($registration->respondent_number) {
            $raw = (string) $registration->respondent_number;
            if (strpos($raw, '-') !== false) {
                $last = substr($raw, strrpos($raw, '-') + 1);
                return (string) ((int) $last);
            }
            return $raw;
        }

        $tenantId = $registration->tenant_id;
        $barangayId = $registration->barangay_id;

        if (! $tenantId || ! $barangayId) {
            throw new \RuntimeException('Cannot assign respondent number without tenant and barangay.');
        }

        $respondentNumber = DB::transaction(function () use ($tenantId, $barangayId, $registration) {
            DB::table('barangays')->where('id', $barangayId)->lockForUpdate()->first();

            $freshReg = KabataanRegistration::where('id', $registration->id)->lockForUpdate()->first();
            if ($freshReg && $freshReg->respondent_number) {
                $raw = (string) $freshReg->respondent_number;
                if (strpos($raw, '-') !== false) {
                    $last = substr($raw, strrpos($raw, '-') + 1);
                    return (string) ((int) $last);
                }
                return $raw;
            }

            try {
                $row = DB::selectOne(
                    'SELECT generate_respondent_number(?, ?) AS respondent_number',
                    [$tenantId, $barangayId]
                );
                $generatedNumber = $row->respondent_number ?? null;
            } catch (\Throwable $e) {
                $generatedNumber = null;
            }

            if (! $generatedNumber) {
                $maxSeq = DB::table('kabataan_registrations')
                    ->where('tenant_id', $tenantId)
                    ->where('barangay_id', $barangayId)
                    ->whereNotNull('respondent_number')
                    ->max('respondent_sequence');

                $nextSeq = ($maxSeq ? (int) $maxSeq : 0) + 1;
                $generatedNumber = (string) $nextSeq;
            }

            $sequence = (int) (strpos((string) $generatedNumber, '-') !== false
                ? substr((string) $generatedNumber, strrpos((string) $generatedNumber, '-') + 1)
                : $generatedNumber);

            $finalNumber = (string) $sequence;

            $formData = $registration->form_data ?? [];
            $formData['respondent_number'] = $finalNumber;

            KabataanRegistration::where('id', $registration->id)->update([
                'respondent_number' => $finalNumber,
                'respondent_sequence' => $sequence,
                'form_data' => $formData,
            ]);

            KkSurveyResponse::where('registration_id', $registration->id)->update([
                'respondent_number' => $finalNumber,
            ]);

            return $finalNumber;
        });

        return (string) $respondentNumber;
    }

    public static function displaySequence(?int $sequence, ?string $fullNumber = null): string
    {
        if ($fullNumber !== null && $fullNumber !== '' && $fullNumber !== '—') {
            $last = strrpos($fullNumber, '-') !== false
                ? substr($fullNumber, strrpos($fullNumber, '-') + 1)
                : $fullNumber;

            if ($last !== '') {
                return (string) ((int) $last);
            }
        }

        if ($sequence) {
            return (string) $sequence;
        }

        return '—';
    }
}
