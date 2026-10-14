<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class KabataanRegistrationNameLimits
{
    public const APPLICATION_MAX = 150;

    public const CACHE_KEY = 'kkprofiling.registration_name_max';

    /**
     * Longest name each field may store. This is the application limit of 150,
     * or the shortest live varchar among the tables that persist those fields.
     */
    public static function max(): int
    {
        return (int) Cache::remember(self::CACHE_KEY, 60, function () {
            $limits = [self::APPLICATION_MAX];

            foreach ([
                ['kabataan_registrations', ['first_name', 'middle_name', 'last_name']],
                ['kk_survey_responses', ['first_name', 'middle_name', 'last_name']],
            ] as [$table, $columns]) {
                $columnMax = self::columnMax($table, $columns);
                if ($columnMax !== null) {
                    $limits[] = $columnMax;
                }
            }

            return min($limits);
        });
    }

    /**
     * Printed signature line is First + Middle + Last plus a short suffix.
     * 150 × 3, the spaces between them, and a suffix still fit under this cap.
     */
    public const SIGNATURE_NAME_MAX = 500;

    public static function message(): string
    {
        return self::APPLICATION_MAX.' maximum characters only.';
    }

    public static function signatureNameMax(): int
    {
        return self::SIGNATURE_NAME_MAX;
    }

    /**
     * @return array<int, string>
     */
    public static function rules(bool $required): array
    {
        $rules = ['string', 'min:2', 'max:'.self::APPLICATION_MAX, "regex:/^[\\p{L}.'\\-\\s]+$/u"];

        array_unshift($rules, $required ? 'required' : 'nullable');

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $names
     * @return array{last_name: string, first_name: string, middle_name: ?string}
     */
    public static function assertStored(array $names): array
    {
        $max = min(self::APPLICATION_MAX, self::max());
        $stored = [
            'last_name' => self::normalize($names['last_name'] ?? ''),
            'first_name' => self::normalize($names['first_name'] ?? ''),
            'middle_name' => self::normalize($names['middle_name'] ?? ''),
        ];
        $stored['middle_name'] = $stored['middle_name'] !== '' ? $stored['middle_name'] : null;

        $errors = [];
        foreach (['last_name', 'first_name', 'middle_name'] as $field) {
            $value = (string) ($stored[$field] ?? '');
            if ($value !== '' && mb_strlen($value) > $max) {
                $errors[$field] = [$max.' maximum characters only.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $stored;
    }

    public static function usersNameMax(): int
    {
        return self::columnMax('users', ['name']) ?? 255;
    }

    private static function normalize(mixed $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return is_string($normalized) ? $normalized : '';
    }

    /**
     * @param  array<int, string>  $columns
     */
    private static function columnMax(string $table, array $columns): ?int
    {
        try {
            if (! Schema::hasTable($table)) {
                return null;
            }

            $driver = Schema::getConnection()->getDriverName();
            if (! in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
                return null;
            }

            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $schemaSql = $driver === 'pgsql'
                ? 'table_schema = current_schema()'
                : 'table_schema = DATABASE()';

            $row = DB::selectOne(
                "SELECT MIN(character_maximum_length) AS name_max
                 FROM information_schema.columns
                 WHERE {$schemaSql}
                   AND table_name = ?
                   AND column_name IN ({$placeholders})",
                array_merge([$table], $columns)
            );

            $max = (int) ($row->name_max ?? 0);

            return $max >= 2 ? $max : null;
        } catch (Throwable) {
            return null;
        }
    }
}
