<?php

namespace App\Models;

use Database\Factories\ResultSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'advisor_profile_id',
    'student_set_id',
    'academic_session_id',
    'academic_year_semester_id',
    'created_by_user_id',
    'status',
    'current_revision_number',
    'published_at',
])]
class ResultSheet extends Model
{
    /** @use HasFactory<ResultSheetFactory> */
    use HasFactory;

    public const string StatusDraft = 'draft';

    public const string StatusPublished = 'published';

    /**
     * @return BelongsTo<AdvisorProfile, $this>
     */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /**
     * @return BelongsTo<StudentSet, $this>
     */
    public function studentSet(): BelongsTo
    {
        return $this->belongsTo(StudentSet::class);
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

    /**
     * @return HasMany<ResultSheetRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ResultSheetRevision::class)->orderBy('revision_number');
    }

    public function canBeEditedByAdvisor(): bool
    {
        return $this->status === self::StatusDraft;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_revision_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
