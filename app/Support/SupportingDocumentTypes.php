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
}
