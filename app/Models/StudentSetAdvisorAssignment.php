<?php

namespace App\Models;

use Database\Factories\StudentSetAdvisorAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'department_id',
    'entry_academic_session_id',
    'staff_profile_id',
    'assigned_by_staff_profile_id',
    'starts_academic_session_id',
    'ends_academic_session_id',
    'active_marker',
])]
class StudentSetAdvisorAssignment extends Model
{
    /** @use HasFactory<StudentSetAdvisorAssignmentFactory> */
    use HasFactory;

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
     * @return BelongsTo<StaffProfile, $this>
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function assignedByStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'assigned_by_staff_profile_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function startsAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'starts_academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function endsAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'ends_academic_session_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_marker' => 'integer',
        ];
    }
}
