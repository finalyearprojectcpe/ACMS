<?php

namespace App\Models;

use Database\Factories\CourseRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_profile_id', 'academic_session_semester_id', 'status', 'feedback_note', 'submitted_at', 'reviewed_at'])]
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
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * @return BelongsTo<AcademicSessionSemester, $this>
     */
    public function academicSessionSemester(): BelongsTo
    {
        return $this->belongsTo(AcademicSessionSemester::class);
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

    /** @return array{registrationNumber: string, sessionYears: string, semester: string} */
    public function reviewRouteParameters(): array
    {
        $this->loadMissing(['studentProfile', 'academicSessionSemester.academicSession', 'academicSessionSemester.semester']);
        $session = $this->academicSessionSemester->academicSession;

        return [
            'registrationNumber' => $this->studentProfile->registration_number,
            'sessionYears' => $session->start_year.$session->end_year,
            'semester' => $this->academicSessionSemester->semester->slug,
        ];
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
