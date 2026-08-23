<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'willing_to_join_group_chat',
        'consent_given',
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

                $bool = filter_var($model->attributes[$column], FILTER_VALIDATE_BOOLEAN);
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
        'facebook_profile_url',
        'willing_to_join_group_chat',
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

    protected function willingToJoinGroupChat(): Attribute
    {
        return $this->pgBooleanAttribute();
    }

    protected function consentGiven(): Attribute
    {
        return $this->pgBooleanAttribute();
    }

    private function pgBooleanAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            set: fn (mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KabataanRegistration::class, 'kabataan_registration_id');
    }
}
