<?php

namespace App\Models;

use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'department_id'])]
class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use HasFactory;

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
     * @return HasOne<Department, $this>
     */
    public function headedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'hod_staff_profile_id');
    }

    /**
     * @return HasOne<Faculty, $this>
     */
    public function headedFaculty(): HasOne
    {
        return $this->hasOne(Faculty::class, 'head_staff_profile_id');
    }

    /**
     * @return HasOne<Faculty, $this>
     */
    public function pendingHeadedFaculty(): HasOne
    {
        return $this->hasOne(Faculty::class, 'pending_head_staff_profile_id');
    }

    /**
     * @return HasMany<StudentSetAdvisorAssignment, $this>
     */
    public function studentSetAdvisorAssignments(): HasMany
    {
        return $this->hasMany(StudentSetAdvisorAssignment::class);
    }

    /**
     * @return HasMany<StudentSetAdvisorAssignment, $this>
     */
    public function activeStudentSetAdvisorAssignments(): HasMany
    {
        return $this->studentSetAdvisorAssignments()->where('active_marker', 1);
    }

    /**
     * @return HasMany<Due, $this>
     */
    public function createdDues(): HasMany
    {
        return $this->hasMany(Due::class, 'created_by_staff_profile_id');
    }

    /**
     * @return HasMany<PaymentAccount, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * @return HasMany<CourseRegistration, $this>
     */
    public function reviewedCourseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class, 'reviewer_staff_profile_id');
    }

    /**
     * @return BelongsToMany<AcademicSession, $this>
     */
    public function advisedEntryAcademicSessions(): BelongsToMany
    {
        return $this->belongsToMany(AcademicSession::class, 'student_set_advisor_assignments', 'staff_profile_id', 'entry_academic_session_id')
            ->withPivot(['department_id', 'starts_academic_session_id', 'ends_academic_session_id', 'active_marker'])
            ->wherePivot('active_marker', 1)
            ->withTimestamps();
    }

    public function isHeadOfDepartment(): bool
    {
        return $this->headedDepartment()->exists();
    }

    public function isFacultyHead(): bool
    {
        return $this->headedFaculty()->exists();
    }
}
