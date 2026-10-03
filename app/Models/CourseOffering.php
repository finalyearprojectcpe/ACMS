<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\CourseOfferingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'academic_session_semester_id', 'course_title', 'credit_units', 'is_elective', 'is_retake_only'])]
class CourseOffering extends Model
{
    /** @use HasFactory<CourseOfferingFactory> */
    use HasFactory;

    use HasPublicId;

    protected static function booted(): void
    {
        static::creating(function (CourseOffering $offering): void {
            $course = $offering->course_id === null
                ? null
                : Course::query()->find($offering->course_id);

            $offering->course_title ??= $course?->title;
            $offering->is_elective ??= false;
        });
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<AcademicSessionSemester, $this> */
    public function academicSessionSemester(): BelongsTo
    {
        return $this->belongsTo(AcademicSessionSemester::class);
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

    /** @return HasMany<CourseRegistrationItem, $this> */
    public function registrationItems(): HasMany
    {
        return $this->hasMany(CourseRegistrationItem::class);
    }

    #[Scope]
    protected function availableAsPrerequisiteFor(
        Builder $query,
        StudentSet $studentSet,
        AcademicSessionSemester $targetSemester,
    ): void {
        $targetSemester->loadMissing(['academicSession', 'semester']);

        $query
            ->whereHas('course', fn (Builder $query) => $query->whereBelongsTo($studentSet))
            ->whereHas('academicSessionSemester', function (Builder $query) use ($targetSemester): void {
                $query->whereHas('academicSession', fn (Builder $query) => $query
                    ->where('start_year', '<', $targetSemester->academicSession->start_year))
                    ->orWhere(function (Builder $query) use ($targetSemester): void {
                        $query->where('academic_session_id', $targetSemester->academic_session_id)
                            ->whereHas('semester', fn (Builder $query) => $query
                                ->where('sort_order', '<', $targetSemester->semester->sort_order));
                    });
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_units' => 'integer',
            'is_elective' => 'boolean',
            'is_retake_only' => 'boolean',
            'results_published_at' => 'datetime',
        ];
    }
}
