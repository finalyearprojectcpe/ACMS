<?php

namespace Database\Factories;

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

        return [
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
            'course_id' => Course::factory()->create(['school_id' => $school->id])->id,
            'academic_session_id' => AcademicSession::factory()->create(['school_id' => $school->id])->id,
            'academic_year_semester_id' => AcademicYearSemester::factory(),
            'is_mandatory' => true,
        ];
    }
}
