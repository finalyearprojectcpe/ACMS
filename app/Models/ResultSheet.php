<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'school_id',
    'department_id',
    'entry_academic_session_id',
    'academic_session_id',
    'academic_year_semester_id',
    'created_by_user_id',
    'submitted_by_user_id',
    'reviewed_by_user_id',
    'status',
    'correction_note',
    'submitted_at',
    'approved_at',
    'returned_at',
])]
class ResultSheet extends Model
{
    public const string StatusDraft = 'draft';

    public const string StatusSubmitted = 'submitted';

    public const string StatusReturned = 'returned';

    public const string StatusApproved = 'approved';

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function entryAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'entry_academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<AcademicYearSemester, $this>
     */
    public function academicYearSemester(): BelongsTo
    {
        return $this->belongsTo(AcademicYearSemester::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return HasMany<ResultSheetCourse, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(ResultSheetCourse::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<StudentSemesterResult, $this>
     */
    public function studentResults(): HasMany
    {
        return $this->hasMany(StudentSemesterResult::class);
    }

    public function canBeEditedByAdvisor(): bool
    {
        return in_array($this->status, [self::StatusDraft, self::StatusReturned], true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
