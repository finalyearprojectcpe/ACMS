<?php

namespace Database\Factories;

use App\CourseCategory;
use App\Models\AcademicSession;
use App\Models\AcademicYearSemester;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Faculty;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseOffering>
 */
class CourseOfferingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $school = School::factory()->create();
        $owner = Faculty::factory()->create(['school_id' => $school->id]);

        $course = Course::factory()->create(['school_id' => $school->id]);

        return [
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
            'course_id' => $course->id,
            'academic_session_id' => AcademicSession::factory()->create(['school_id' => $school->id])->id,
            'academic_year_semester_id' => AcademicYearSemester::factory(),
            'course_title' => fn (array $attributes): string => Course::query()->findOrFail($attributes['course_id'])->title,
            'credit_units' => fn (array $attributes): int => (int) Course::query()->findOrFail($attributes['course_id'])->credit_units,
            'category' => fn (array $attributes): CourseCategory => $attributes['owner_type'] === Faculty::class
                ? CourseCategory::Faculty
                : CourseCategory::Departmental,
            'archived_at' => null,
        ];
    }
}
