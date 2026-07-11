<?php

namespace App\Models;

use App\CourseCategory;
use Database\Factories\CourseOfferingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['owner_type', 'owner_id', 'course_id', 'academic_session_id', 'academic_year_semester_id', 'course_title', 'credit_units', 'category', 'archived_at'])]
class CourseOffering extends Model
{
    /** @use HasFactory<CourseOfferingFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (CourseOffering $offering): void {
            $course = $offering->course_id === null
                ? null
                : Course::query()->find($offering->course_id);

            $offering->course_title ??= $course?->title;
            $offering->credit_units ??= $course?->credit_units;
            $offering->category ??= $offering->owner_type === Faculty::class
                ? CourseCategory::Faculty
                : CourseCategory::Departmental;
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
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
     * @return BelongsToMany<Course, $this>
     */
    public function prerequisiteCourses(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_offering_prerequisites',
            'course_offering_id',
            'prerequisite_course_id',
        )->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CourseCategory::class,
            'credit_units' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
