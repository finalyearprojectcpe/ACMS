<?php

namespace App\Models;

use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'user_id', 'department_id', 'current_academic_year_id', 'entry_academic_session_id', 'registration_number'])]
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function currentAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'current_academic_year_id');
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function currentLevel(): BelongsTo
    {
        return $this->currentAcademicYear();
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function entryAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'entry_academic_session_id');
    }

    /**
     * @return HasMany<StudentSemesterResult, $this>
     */
    public function semesterResults(): HasMany
    {
        return $this->hasMany(StudentSemesterResult::class);
    }

    /**
     * @return HasMany<StudentDueAssignment, $this>
     */
    public function dueAssignments(): HasMany
    {
        return $this->hasMany(StudentDueAssignment::class);
    }

    /**
     * @return HasMany<DuePayment, $this>
     */
    public function duePayments(): HasMany
    {
        return $this->hasMany(DuePayment::class);
    }

    /**
     * @return HasMany<CourseRegistration, $this>
     */
    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }
}
