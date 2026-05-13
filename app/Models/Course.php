<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['code', 'title', 'slug', 'credit_units'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * @return HasMany<CourseOffering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_course_id',
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function dependentCourses(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_prerequisites',
            'prerequisite_course_id',
            'course_id',
        )->withTimestamps();
    }

    public function addPrerequisite(Course $course): void
    {
        if ((int) $this->getKey() === (int) $course->getKey()) {
            throw new InvalidArgumentException('A course cannot require itself as a prerequisite.');
        }

        $this->prerequisites()->syncWithoutDetaching([$course->getKey()]);
    }
}
