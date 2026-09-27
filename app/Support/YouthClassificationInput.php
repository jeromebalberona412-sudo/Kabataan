<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class YouthClassificationInput
{
    /**
     * @return list<string>
     */
    public static function specificNeeds(): array
    {
        return [
            'Person w/ Disability',
            'Children in Conflict w/ Law',
            'Indigenous People',
        ];
    }

    /**
     * Youth w/ Specific Needs is valid only when one of the three needs is selected.
     */
    public static function assertValid(string $value): void
    {
        $main = ['In School Youth', 'Out of School Youth', 'Working Youth'];
        $needs = self::specificNeeds();

        if (in_array($value, $main, true)) {
            return;
        }

        if ($value === 'Youth w/ Specific Needs') {
            throw ValidationException::withMessages([
                'youth_classification' => ['Please select Person w/ Disability, Children In Conflict w/ Law, or Indigenous People.'],
            ]);
        }

        if (str_starts_with($value, 'Youth w/ Specific Needs|')) {
            $need = substr($value, strlen('Youth w/ Specific Needs|'));
            if (in_array($need, $needs, true)) {
                return;
            }
        }

        if (in_array($value, $needs, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'youth_classification' => ['Please select a valid Youth Classification.'],
        ]);
    }
}
