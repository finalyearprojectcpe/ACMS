<?php

namespace App\Models;

use Database\Factories\CourseRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'department_id', 'student_profile_id', 'academic_session_id', 'academic_year_semester_id', 'reviewer_staff_profile_id', 'status', 'feedback_note', 'submitted_at', 'reviewed_at'])]
class CourseRegistration extends Model
{
    public const string StatusDraft = 'draft';

    public const string StatusSubmitted = 'submitted';

    public const string StatusApproved = 'approved';

    public const string StatusReturned = 'returned';

    /** @use HasFactory<CourseRegistrationFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => self::StatusDraft,
    ];

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
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
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
     * @return BelongsTo<StaffProfile, $this>
     */
    public function reviewerStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'reviewer_staff_profile_id');
    }

    /**
     * @return HasMany<CourseRegistrationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CourseRegistrationItem::class);
    }

    public function isEditableByStudent(): bool
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
            'reviewed_at' => 'datetime',
        ];
    }
}
