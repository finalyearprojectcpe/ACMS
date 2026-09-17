<?php

namespace Database\Factories;

use App\CourseCategory;
use App\Models\AcademicSession;
use App\Models\AcademicYearSemester;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\StudentSet;
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
        $advisor = AdvisorProfile::factory()->create();
        $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
        $course = Course::factory()->create(['advisor_profile_id' => $advisor->id]);

        return [
            'student_set_id' => $set->id,
            'course_id' => $course->id,
            'academic_session_id' => AcademicSession::factory()->create([
                'advisor_profile_id' => $advisor->id,
            ])->id,
            'academic_year_semester_id' => AcademicYearSemester::factory(),
            'course_title' => fn (array $attributes): string => Course::query()->findOrFail($attributes['course_id'])->title,
            'credit_units' => fn (array $attributes): int => (int) Course::query()->findOrFail($attributes['course_id'])->credit_units,
            'category' => CourseCategory::Normal,
            'archived_at' => null,
        ];
    }
}
