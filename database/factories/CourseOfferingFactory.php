<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
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
        $set = StudentSet::factory()->create();
        $course = Course::factory()->create(['student_set_id' => $set->id]);
        $session = AcademicSession::factory()->create(['student_set_id' => $set->id]);
        $semester = AcademicSessionSemester::factory()->create([
            'academic_session_id' => $session->id,
        ]);

        return [
            'course_id' => $course->id,
            'academic_session_semester_id' => $semester->id,
            'course_title' => fn (array $attributes): string => Course::query()->findOrFail($attributes['course_id'])->title,
            'credit_units' => fake()->numberBetween(1, 4),
            'is_elective' => false,
        ];
    }
}
