<?php

namespace App\Support;

final class SupportingDocumentTypes
{
    public const SCHOOL_ID = 'school_id';

    public const NATIONAL_ID = 'national_id';

    public const VOTERS_ID = 'voters_id';

    public const PHILHEALTH_ID = 'philhealth_id';

    public const OTHER_ID = 'other_id';

    public const SIDE_FRONT = 'front';

    public const SIDE_BACK = 'back';

    /**
     * @return list<string>
     */
    public static function sides(): array
    {
        return [self::SIDE_FRONT, self::SIDE_BACK];
    }

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return [
            self::SCHOOL_ID,
            self::NATIONAL_ID,
            self::VOTERS_ID,
            self::PHILHEALTH_ID,
            self::OTHER_ID,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::SCHOOL_ID => 'School ID',
            self::NATIONAL_ID => 'PhilSys / National ID',
            self::VOTERS_ID => "Voter's ID",
            self::PHILHEALTH_ID => 'PhilHealth ID',
            self::OTHER_ID => 'Other valid proof of identity or residency',
        ];
    }

    public static function label(string $type): string
    {
        if ($type === 'barangay_clearance') {
            return 'Barangay Clearance';
        }

        return self::labels()[$type] ?? 'Supporting Document';
    }

    public static function normalize(mixed $value): ?string
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return null;
        }

        $compact = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $raw)));
        $underscored = str_replace(' ', '_', $compact);

        $aliases = [
            'school_id' => self::SCHOOL_ID,
            'schoolid' => self::SCHOOL_ID,
            'school id' => self::SCHOOL_ID,
            'national_id' => self::NATIONAL_ID,
            'nationalid' => self::NATIONAL_ID,
            'national id' => self::NATIONAL_ID,
            'philid' => self::NATIONAL_ID,
            'phil_id' => self::NATIONAL_ID,
            'phil id' => self::NATIONAL_ID,
            'philsys' => self::NATIONAL_ID,
            'philippine_national_id' => self::NATIONAL_ID,
            'philippine national id' => self::NATIONAL_ID,
            'philsys_national_id' => self::NATIONAL_ID,
            'philsys / national id' => self::NATIONAL_ID,
            'voters_id' => self::VOTERS_ID,
            'voter_id' => self::VOTERS_ID,
            'voter id' => self::VOTERS_ID,
            "voter's id" => self::VOTERS_ID,
            'voters id' => self::VOTERS_ID,
            'philhealth_id' => self::PHILHEALTH_ID,
            'philhealth' => self::PHILHEALTH_ID,
            'philhealth id' => self::PHILHEALTH_ID,
            'other_id' => self::OTHER_ID,
            'other id' => self::OTHER_ID,
        ];

        $normalized = $aliases[$raw] ?? $aliases[$compact] ?? $aliases[$underscored] ?? null;

        if ($normalized !== null) {
            return $normalized;
        }

        return in_array($underscored, self::allowed(), true) ? $underscored : null;
    }

    /**
     * @param  list<mixed>|mixed  $values
     * @return list<string>
     */
    public static function normalizeList(mixed $values): array
    {
        $items = is_array($values) ? $values : [$values];
        $normalized = [];

        foreach ($items as $value) {
            $type = self::normalize($value);
            if ($type !== null) {
                $normalized[] = $type;
            }
        }

        return array_values(array_unique($normalized));
    }

    public static function questionAcceptsImages(array $question): bool
    {
        if (($question['type'] ?? '') !== 'file') {
            return false;
        }

        $fileType = strtolower(trim((string) ($question['file_type'] ?? 'pdf')));

        return in_array($fileType, ['image', 'pdf_or_image'], true);
    }

    /**
     * ID types this file question accepts. Empty when the requirement is not an identifiable ID image.
     *
     * @return list<string>
     */
    public static function acceptedIdTypesForQuestion(array $question): array
    {
        if (! self::questionAcceptsImages($question)) {
            return [];
        }

        $explicit = self::normalizeList($question['accepted_id_types'] ?? []);
        if ($explicit !== []) {
            return $explicit;
        }

        $fromLabel = self::normalize((string) ($question['label'] ?? ''));

        return $fromLabel === null ? [] : [$fromLabel];
    }
}
