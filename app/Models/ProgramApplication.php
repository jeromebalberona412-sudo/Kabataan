<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramApplication extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'program_id',
        'kabataan_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'age',
        'sex',
        'civil_status',
        'purok',
        'barangay',
        'email',
        'contact_number',
        'parent_guardian_name',
        'parent_occupation',
        'parent_income',
        'school_name',
        'grade_level',
        'course',
        'gwa',
        'custom_answers',
        'system_field_answers',
        'scholar_status',
        'required_documents',
        'purpose',
        'remarks',
        'status',
        'cancel_reason',
        'payment_status',
        'rejection_reason',
        'rejection_reasons',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'custom_answers' => 'array',
            'system_field_answers' => 'array',
            'required_documents' => 'array',
            'rejection_reasons' => 'array',
            'parent_income' => 'decimal:2',
            'gwa' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function scheduleProgram(): BelongsTo
    {
        return $this->belongsTo(ScheduleProgram::class, 'program_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kabataan_id');
    }
}
