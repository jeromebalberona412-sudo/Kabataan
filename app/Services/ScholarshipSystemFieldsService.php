<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ScholarshipSystemFieldsService
{
    private const NAME_REGEX = '/^(?!\s)[A-Za-z.\-\s]+$/';

    private const NAME_NO_SPACE_REGEX = '/^[A-Za-z.\-]+$/';

    private const FIRST_NAME_REGEX = '/^(?!\s)[A-Za-z.\-]+(\s[A-Za-z.\-]+)*$/';

    /** @var list<string> */
    private const NAME_NO_SPACE_FIELDS = [
        'mother_middle_name', 'father_middle_name', 'guardian_middle_name',
        'mother_last_name', 'father_last_name', 'guardian_last_name',
    ];

    /** @var list<string> */
    private const FIRST_NAME_FIELDS = [
        'mother_first_name', 'father_first_name', 'guardian_first_name',
    ];

    private const NAME_MAX_LENGTH = 50;

    private const STRAND_MAX_LENGTH = 100;

    private const STRAND_ABBREVIATION_MAX_LENGTH = 50;

    /** @var list<string> */
    private const YEAR_LEVEL_HS = ['Grade 11', 'Grade 12', 'Other'];

    /** @var list<string> */
    private const YEAR_LEVEL_COLLEGE = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Other'];

    /** @var list<string> */
    private const YEAR_LEVEL_VOCATIONAL = ['1st Year', '2nd Year', 'Other'];

    private const CONTACT_REGEX = '/^09\d{9}$/';

    private const SCHOOL_TEXT_MIN = 20;

    private const SCHOOL_TEXT_MAX = 100;

    private const OCCUPATION_OTHER_VALUE = 'Other Occupation';

    private const OCCUPATION_OTHER_MIN = 3;

    private const OCCUPATION_OTHER_MAX = 100;

    /** @var list<string> */
    private const FAMILY_MONTHLY_INCOME_OPTIONS = [
        '₱5,000',
        '₱10,000',
        '₱20,000',
        '₱30,000',
        '₱40,000',
        '₱50,000',
        '₱50,000 and above',
    ];

    /** @var list<string> */
    private const SCHOOL_TEXT_FIELDS = [
        'elementary_school', 'elementary_address',
        'secondary_school', 'secondary_address',
        'senior_high_school', 'senior_high_address',
    ];

    /** @var list<string> */
    private const ELEMENTARY_ONLY = ['Elementary Level', 'Elementary Grad'];

    /** @var list<string> */
    private const HIGH_SCHOOL_TRACK = ['High School Grad', 'High School Level'];

    /** @var list<string> */
    private const COLLEGE_TRACK = ['College Level', 'College Grad', 'Vocational Grad', 'Masters Level', 'Masters Grad', 'Doctorate Level', 'Doctorate Graduate'];

    /** @var list<string> */
    private const ADDITIONAL_EDUCATION = ['High School Level', 'College Level', 'College Grad', 'Vocational Grad'];

    /** @var list<string> */
    private const STRAND_COLLEGE_TRACK = ['College Level', 'College Grad', 'Vocational Grad'];

    /**
     * @return list<array{id: string, label: string, type: string, required: bool, section: string}>
     */
    public static function fieldDefinitions(): array
    {
        return [
            ['id' => 'elementary_school', 'label' => 'Elementary School', 'type' => 'text', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'elementary_address', 'label' => 'School Address', 'type' => 'text', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'elementary_year_graduated', 'label' => 'Year Graduated', 'type' => 'year', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'secondary_school', 'label' => 'Secondary School', 'type' => 'text', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'secondary_address', 'label' => 'School Address', 'type' => 'text', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'secondary_year_graduated', 'label' => 'Year Graduated', 'type' => 'year', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'senior_high_school', 'label' => 'Senior High School', 'type' => 'text', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'senior_high_address', 'label' => 'School Address', 'type' => 'text', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'senior_high_year_graduated', 'label' => 'Year Graduated', 'type' => 'year', 'required' => true, 'section' => 'educational_background'],
            ['id' => 'mother_first_name', 'label' => "Mother's First Name", 'type' => 'name', 'required' => true, 'section' => 'background_information'],
            ['id' => 'mother_middle_name', 'label' => "Mother's Middle Name", 'type' => 'name', 'required' => false, 'section' => 'background_information'],
            ['id' => 'mother_last_name', 'label' => "Mother's Last Name", 'type' => 'name', 'required' => true, 'section' => 'background_information'],
            ['id' => 'mother_occupation', 'label' => "Mother's Occupation", 'type' => 'select', 'required' => true, 'section' => 'background_information'],
            ['id' => 'mother_occupation_other', 'label' => "Mother's Other Occupation", 'type' => 'text', 'required' => true, 'section' => 'background_information'],
            ['id' => 'mother_contact_number', 'label' => "Mother's Contact No.", 'type' => 'contact', 'required' => true, 'section' => 'background_information'],
            ['id' => 'father_first_name', 'label' => "Father's First Name", 'type' => 'name', 'required' => true, 'section' => 'background_information'],
            ['id' => 'father_middle_name', 'label' => "Father's Middle Name", 'type' => 'name', 'required' => false, 'section' => 'background_information'],
            ['id' => 'father_last_name', 'label' => "Father's Last Name", 'type' => 'name', 'required' => true, 'section' => 'background_information'],
            ['id' => 'father_occupation', 'label' => "Father's Occupation", 'type' => 'select', 'required' => true, 'section' => 'background_information'],
            ['id' => 'father_occupation_other', 'label' => "Father's Other Occupation", 'type' => 'text', 'required' => true, 'section' => 'background_information'],
            ['id' => 'father_contact_number', 'label' => "Father's Contact No.", 'type' => 'contact', 'required' => true, 'section' => 'background_information'],
            ['id' => 'guardian_first_name', 'label' => "Guardian's First Name", 'type' => 'name', 'required' => false, 'section' => 'background_information'],
            ['id' => 'guardian_middle_name', 'label' => "Guardian's Middle Name", 'type' => 'name', 'required' => false, 'section' => 'background_information'],
            ['id' => 'guardian_last_name', 'label' => "Guardian's Last Name", 'type' => 'name', 'required' => false, 'section' => 'background_information'],
            ['id' => 'guardian_occupation', 'label' => "Guardian's Occupation", 'type' => 'select', 'required' => false, 'section' => 'background_information'],
            ['id' => 'guardian_occupation_other', 'label' => "Guardian's Other Occupation", 'type' => 'text', 'required' => true, 'section' => 'background_information'],
            ['id' => 'guardian_relation', 'label' => 'Relation to Guardian', 'type' => 'text', 'required' => false, 'section' => 'background_information'],
            ['id' => 'guardian_contact_number', 'label' => "Guardian's Contact No.", 'type' => 'contact', 'required' => false, 'section' => 'background_information'],
            ['id' => 'annual_family_gross_income', 'label' => 'Family Monthly Income', 'type' => 'select', 'required' => true, 'section' => 'background_information'],
            ['id' => 'strand', 'label' => 'Strand / Course', 'type' => 'text', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'strand_abbreviation', 'label' => 'Strand / Course Abbreviation', 'type' => 'text', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'year_level', 'label' => 'Year Level', 'type' => 'text', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'units_enrolled', 'label' => 'Units Enrolled', 'type' => 'number', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'expected_graduation_year', 'label' => 'Expected Year of Graduation', 'type' => 'year', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'graduating', 'label' => 'Graduating?', 'type' => 'radio', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'semester_of_graduation', 'label' => 'Semester of Graduation', 'type' => 'text', 'required' => false, 'section' => 'additional_information'],
            ['id' => 'school_name', 'label' => 'School Name', 'type' => 'text', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'school_abbreviation', 'label' => 'School Abbreviation', 'type' => 'text', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'school_address', 'label' => 'School Address', 'type' => 'text', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'receiving_gov_aid', 'label' => 'Government scholarship recipient', 'type' => 'radio', 'required' => true, 'section' => 'additional_information'],
            ['id' => 'gov_aid_program_name', 'label' => 'Government aid program name', 'type' => 'text', 'required' => false, 'section' => 'additional_information'],
            ['id' => 'family_on_scholarship', 'label' => 'Family on scholarship program', 'type' => 'radio', 'required' => true, 'section' => 'additional_information'],
        ];
    }

    private const UNITS_ENROLLED_MAX = 100;

    private const GRADUATION_YEAR_AHEAD_LIMIT = 1;

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $applicantContext
     * @return array<string, mixed>
     */
    public function validate(array $answers, string $kkEducation, array $applicantContext = []): array
    {
        $normalized = [];
        foreach ($answers as $key => $value) {
            $normalized[(string) $key] = is_string($value) ? trim($value) : $value;
        }

        $errors = [];

        foreach (self::fieldDefinitions() as $field) {
            if (! $this->isFieldVisible($field['id'], $normalized, $kkEducation)) {
                continue;
            }

            $value = $normalized[$field['id']] ?? '';
            $message = $this->validateField($field, $value, $normalized, $kkEducation, $applicantContext);

            if ($message !== '') {
                $errors[$field['id']] = [$message];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if (($normalized['graduating'] ?? '') !== 'Yes') {
            $normalized['semester_of_graduation'] = 'N/A';
            $normalized['expected_graduation_year'] = '';
        }

        if (($normalized['receiving_gov_aid'] ?? '') !== 'Yes') {
            $normalized['gov_aid_program_name'] = '';
        }

        foreach (['mother', 'father', 'guardian'] as $prefix) {
            $occupationKey = "{$prefix}_occupation";
            $otherKey = "{$prefix}_occupation_other";
            if (($normalized[$occupationKey] ?? '') !== self::OCCUPATION_OTHER_VALUE) {
                $normalized[$otherKey] = '';
            } elseif (isset($normalized[$otherKey]) && is_string($normalized[$otherKey])) {
                $normalized[$otherKey] = mb_strtoupper(trim($normalized[$otherKey]));
            }
        }

        foreach (self::fieldDefinitions() as $field) {
            if (! isset($normalized[$field['id']]) || ! is_string($normalized[$field['id']])) {
                continue;
            }

            if (! in_array($field['type'], ['text', 'name', 'suffix'], true)) {
                continue;
            }

            if (str_ends_with($field['id'], '_occupation_other')) {
                if (! $this->isFieldVisible($field['id'], $normalized, $kkEducation)) {
                    continue;
                }
                $normalized[$field['id']] = mb_strtoupper(trim((string) $normalized[$field['id']]));

                continue;
            }

            if (! $this->isFieldVisible($field['id'], $normalized, $kkEducation)) {
                continue;
            }

            $normalized[$field['id']] = mb_strtoupper(trim($normalized[$field['id']]));
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function isFieldVisible(string $fieldId, array $values, string $education): bool
    {
        $education = trim($education);

        if (str_starts_with($fieldId, 'elementary_')) {
            return $this->isSchoolBlockVisible('elementary', $education);
        }

        if (str_starts_with($fieldId, 'secondary_')) {
            return $this->isSchoolBlockVisible('secondary', $education);
        }

        if (str_starts_with($fieldId, 'senior_high_')) {
            return $this->isSchoolBlockVisible('senior_high', $education);
        }

        $additionalIds = [
            'strand', 'strand_abbreviation', 'year_level', 'units_enrolled',
            'expected_graduation_year', 'graduating', 'semester_of_graduation',
            'school_name', 'school_abbreviation', 'school_address',
        ];

        if (in_array($fieldId, $additionalIds, true)) {
            if (! in_array($education, self::ADDITIONAL_EDUCATION, true)) {
                return false;
            }
        }

        if ($fieldId === 'strand_abbreviation') {
            return in_array($education, self::STRAND_COLLEGE_TRACK, true);
        }

        if ($fieldId === 'expected_graduation_year') {
            return ($values['graduating'] ?? '') === 'Yes';
        }

        if ($fieldId === 'semester_of_graduation') {
            return ($values['graduating'] ?? '') === 'Yes';
        }

        if ($fieldId === 'gov_aid_program_name') {
            return ($values['receiving_gov_aid'] ?? '') === 'Yes';
        }

        if (str_ends_with($fieldId, '_occupation_other')) {
            $occupationKey = str_replace('_other', '', $fieldId);

            return ($values[$occupationKey] ?? '') === self::OCCUPATION_OTHER_VALUE;
        }

        return true;
    }

    private function isSchoolBlockVisible(string $blockId, string $education): bool
    {
        if ($education === '') {
            return false;
        }

        if ($blockId === 'elementary') {
            return in_array($education, self::ELEMENTARY_ONLY, true)
                || in_array($education, self::HIGH_SCHOOL_TRACK, true)
                || in_array($education, self::COLLEGE_TRACK, true);
        }

        if ($blockId === 'secondary') {
            return in_array($education, self::HIGH_SCHOOL_TRACK, true)
                || in_array($education, self::COLLEGE_TRACK, true);
        }

        if ($blockId === 'senior_high') {
            return $education === 'High School Level'
                || in_array($education, self::COLLEGE_TRACK, true);
        }

        return false;
    }

    /**
     * @param  array{id: string, label: string, type: string, required: bool}  $field
     * @param  array<string, mixed>  $allAnswers
     * @param  array<string, mixed>  $applicantContext
     */
    private function validateField(
        array $field,
        mixed $value,
        array $allAnswers = [],
        string $kkEducation = '',
        array $applicantContext = [],
    ): string {
        $stringValue = trim((string) ($value ?? ''));

        if ($field['type'] === 'name') {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '' && strlen($stringValue) < 3) {
                return "{$field['label']} must be at least 3 characters.";
            }
            if ($stringValue !== '' && strlen($stringValue) > self::NAME_MAX_LENGTH) {
                return "{$field['label']} must not exceed ".self::NAME_MAX_LENGTH.' characters.';
            }
            if ($stringValue !== '' && in_array($field['id'], self::NAME_NO_SPACE_FIELDS, true)) {
                if (preg_match('/\s/', $stringValue)) {
                    return "{$field['label']} cannot contain spaces.";
                }
                if (! preg_match(self::NAME_NO_SPACE_REGEX, $stringValue)) {
                    return "{$field['label']} has an invalid format.";
                }

                return '';
            }
            if ($stringValue !== '' && in_array($field['id'], self::FIRST_NAME_FIELDS, true)) {
                if (! preg_match(self::FIRST_NAME_REGEX, $stringValue)) {
                    return "{$field['label']} cannot start with a space.";
                }

                return '';
            }
            if ($stringValue !== '' && ! preg_match(self::NAME_REGEX, $stringValue)) {
                return "{$field['label']} has an invalid format.";
            }

            return '';
        }

        if ($field['type'] === 'suffix') {
            if ($stringValue !== '' && strlen($stringValue) > 10) {
                return "{$field['label']} is too long.";
            }
            if ($stringValue !== '' && ! preg_match(self::NAME_REGEX, $stringValue)) {
                return "{$field['label']} has an invalid format.";
            }

            return '';
        }

        if ($field['type'] === 'contact') {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '' && ! preg_match(self::CONTACT_REGEX, $stringValue)) {
                return "{$field['label']} must use format 09XXXXXXXXX.";
            }

            return '';
        }

        if ($field['type'] === 'currency') {
            $digits = preg_replace('/\D+/', '', $stringValue) ?? '';
            if ($digits === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($digits !== '' && strlen($digits) > 10) {
                return "{$field['label']} allows a maximum of 10 digits.";
            }

            return '';
        }

        if (in_array($field['id'], self::SCHOOL_TEXT_FIELDS, true)) {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '' && strlen($stringValue) < self::SCHOOL_TEXT_MIN) {
                return "{$field['label']} must be at least ".self::SCHOOL_TEXT_MIN.' characters.';
            }
            if ($stringValue !== '' && strlen($stringValue) > self::SCHOOL_TEXT_MAX) {
                return "{$field['label']} must not exceed ".self::SCHOOL_TEXT_MAX.' characters.';
            }

            return '';
        }

        if (str_ends_with($field['id'], '_occupation_other')) {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '' && strlen($stringValue) < self::OCCUPATION_OTHER_MIN) {
                return "{$field['label']} must be at least ".self::OCCUPATION_OTHER_MIN.' characters.';
            }
            if ($stringValue !== '' && strlen($stringValue) > self::OCCUPATION_OTHER_MAX) {
                return "{$field['label']} must not exceed ".self::OCCUPATION_OTHER_MAX.' characters.';
            }

            return '';
        }

        if ($field['id'] === 'annual_family_gross_income') {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '' && ! in_array($stringValue, self::FAMILY_MONTHLY_INCOME_OPTIONS, true)) {
                return "{$field['label']} must be a valid income bracket.";
            }

            return '';
        }

        if ($field['type'] === 'year') {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '' && ! preg_match('/^\d{4}$/', $stringValue)) {
                return "{$field['label']} must be a valid 4-digit year.";
            }
            if ($stringValue !== '') {
                $year = (int) $stringValue;
                if ($field['id'] === 'expected_graduation_year') {
                    $bounds = $this->expectedGraduationYearBounds();
                    if ($year < $bounds['min'] || $year > $bounds['max']) {
                        return "{$field['label']} must be between {$bounds['min']} and {$bounds['max']}.";
                    }
                } elseif (str_ends_with($field['id'], '_year_graduated')) {
                    $bounds = $this->graduationYearBounds($field['id'], $allAnswers, $applicantContext);
                    if ($year < $bounds['min'] || $year > $bounds['max']) {
                        return "{$field['label']} must be between {$bounds['min']} and {$bounds['max']} based on your age and education history.";
                    }
                }
            }

            return '';
        }

        if ($field['id'] === 'units_enrolled') {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '') {
                if (! preg_match('/^\d+$/', $stringValue)) {
                    return "{$field['label']} must contain numbers only.";
                }
                $units = (int) $stringValue;
                if ($units < 1) {
                    return "{$field['label']} cannot be negative or zero.";
                }
                if ($units > self::UNITS_ENROLLED_MAX) {
                    return "{$field['label']} must not exceed ".self::UNITS_ENROLLED_MAX.'.';
                }
            }

            return '';
        }

        if ($field['id'] === 'guardian_relation') {
            if ($stringValue !== '' && strlen($stringValue) > 50) {
                return "{$field['label']} must not exceed 50 characters.";
            }

            return '';
        }

        if ($field['id'] === 'strand') {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '' && strlen($stringValue) > self::STRAND_MAX_LENGTH) {
                return "{$field['label']} must not exceed ".self::STRAND_MAX_LENGTH.' characters.';
            }

            return '';
        }

        if ($field['id'] === 'strand_abbreviation') {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '' && preg_match('/\s/', $stringValue)) {
                return "{$field['label']} cannot contain spaces.";
            }
            if ($stringValue !== '' && strlen($stringValue) > self::STRAND_ABBREVIATION_MAX_LENGTH) {
                return "{$field['label']} must not exceed ".self::STRAND_ABBREVIATION_MAX_LENGTH.' characters.';
            }

            return '';
        }

        if ($field['id'] === 'school_address') {
            $raw = (string) ($value ?? '');
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($raw !== '' && $stringValue === '') {
                return "{$field['label']} cannot be spaces only.";
            }

            return '';
        }

        if ($field['id'] === 'year_level') {
            if ($stringValue === '' && $field['required']) {
                return "{$field['label']} is required.";
            }
            if ($stringValue !== '') {
                $options = $this->yearLevelOptions($kkEducation);
                if ($options !== [] && ! in_array($stringValue, $options, true)) {
                    return "{$field['label']} must be a valid selection.";
                }
            }

            return '';
        }

        if ($field['required'] && $stringValue === '') {
            return "{$field['label']} is required.";
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function yearLevelOptions(string $education): array
    {
        $education = trim($education);

        if ($education === 'High School Level') {
            return self::YEAR_LEVEL_HS;
        }

        if (in_array($education, ['College Level', 'College Grad'], true)) {
            return self::YEAR_LEVEL_COLLEGE;
        }

        if ($education === 'Vocational Grad') {
            return self::YEAR_LEVEL_VOCATIONAL;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $applicantContext
     * @return array{min: int, max: int}
     */
    private function graduationYearBounds(string $fieldId, array $answers, array $applicantContext): array
    {
        $currentYear = (int) date('Y');
        $birthYear = $this->resolveBirthYear($applicantContext);
        $minAges = [
            'elementary_year_graduated' => 10,
            'secondary_year_graduated' => 13,
            'senior_high_year_graduated' => 15,
        ];
        $min = $birthYear !== null
            ? $birthYear + ($minAges[$fieldId] ?? 10)
            : 1950;
        $max = $currentYear;

        if ($fieldId === 'secondary_year_graduated') {
            $elementaryYear = (int) ($answers['elementary_year_graduated'] ?? 0);
            if ($elementaryYear > 0) {
                $min = max($min, $elementaryYear + 1);
            }
        }

        if ($fieldId === 'senior_high_year_graduated') {
            $secondaryYear = (int) ($answers['secondary_year_graduated'] ?? 0);
            if ($secondaryYear > 0) {
                $min = max($min, $secondaryYear + 1);
            }
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * @return array{min: int, max: int}
     */
    private function expectedGraduationYearBounds(): array
    {
        $currentYear = (int) date('Y');

        return [
            'min' => $currentYear,
            'max' => $currentYear + self::GRADUATION_YEAR_AHEAD_LIMIT,
        ];
    }

    /**
     * @param  array<string, mixed>  $applicantContext
     */
    private function resolveBirthYear(array $applicantContext): ?int
    {
        $birthday = trim((string) ($applicantContext['birthday'] ?? ''));
        if ($birthday !== '') {
            try {
                return (int) Carbon::parse($birthday)->year;
            } catch (\Throwable) {
                // fall through
            }
        }

        $age = $applicantContext['age'] ?? null;
        if (is_numeric($age) && (int) $age > 0) {
            return (int) date('Y') - (int) $age;
        }

        return null;
    }
}
