<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class KkSurveyResponse extends Model
{
    protected $table = 'kk_survey_responses';

    /** @var list<string> */
    private const BOOLEAN_COLUMNS = [
        'registered_sk_voter',
        'registered_national_voter',
        'attended_kk_assembly',
        'voted_last_sk',
        'consent_given',
    ];

    /**
     * Schema defaults for NOT NULL boolean columns. Never write SQL NULL —
     * that overrides the column default and fails inserts/updates.
     *
     * @var array<string, bool>
     */
    private const BOOLEAN_DEFAULTS = [
        'registered_sk_voter' => false,
        'registered_national_voter' => false,
        'attended_kk_assembly' => false,
        'voted_last_sk' => false,
        'consent_given' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->getConnection()->getDriverName() !== 'pgsql') {
                return;
            }

            foreach (self::BOOLEAN_COLUMNS as $column) {
                if (! array_key_exists($column, $model->attributes)) {
                    continue;
                }

                $raw = $model->attributes[$column];
                if ($raw instanceof Expression) {
                    continue;
                }

                $bool = self::parseNullableBoolean($raw);
                if ($bool === null) {
                    $bool = self::BOOLEAN_DEFAULTS[$column] ?? false;
                }

                $model->attributes[$column] = DB::raw($bool ? 'TRUE' : 'FALSE');
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'barangay_id',
        'kabataan_registration_id',
        'respondent_number',
        'survey_date',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'region',
        'province',
        'municipality',
        'barangay',
        'purok_zone',
        'sex_assigned_at_birth',
        'age',
        'birthdate',
        'email',
        'contact_number',
        'civil_status',
        'youth_age_group',
        'educational_background',
        'youth_classification',
        'work_status',
        'registered_sk_voter',
        'registered_national_voter',
        'attended_kk_assembly',
        'voted_last_sk',
        'kk_assembly_attendance_count',
        'kk_assembly_non_attendance_reason',
        'participant_signature',
        'supporting_documents',
        'consent_given',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'survey_date' => 'date',
            'birthdate' => 'date',
            'supporting_documents' => 'array',
        ];
    }

    protected function registeredSkVoter(): Attribute
    {
        return $this->pgBooleanAttribute();
    }

    protected function registeredNationalVoter(): Attribute
    {
        return $this->pgBooleanAttribute();
    }

    protected function attendedKkAssembly(): Attribute
    {
        return $this->pgBooleanAttribute();
    }

    protected function votedLastSk(): Attribute
    {
        return $this->pgBooleanAttribute();
    }

    protected function consentGiven(): Attribute
    {
        return $this->pgBooleanAttribute();
    }

    public static function parseNullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['true', 't', '1', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['false', 'f', '0', 'no'], true)) {
            return false;
        }

        return null;
    }

    private function pgBooleanAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => self::parseNullableBoolean($value),
            set: fn (mixed $value) => self::parseNullableBoolean($value),
        );
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KabataanRegistration::class, 'kabataan_registration_id');
    }
}
