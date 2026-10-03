<?php

namespace App\Models;

use Database\Factories\CourseRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_profile_id', 'academic_session_semester_id', 'status', 'assigned_by_user_id', 'assigned_at'])]
class CourseRegistration extends Model
{
    public const string StatusDraft = 'draft';

    public const string StatusAssigned = 'assigned';

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
