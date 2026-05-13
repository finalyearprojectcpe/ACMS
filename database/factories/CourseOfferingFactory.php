<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Faculty;
use App\Models\Level;
use App\Models\Semester;
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
        $owner = Faculty::factory()->create();

        return [
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
            'course_id' => Course::factory(),
            'level_id' => Level::factory(),
            'semester_id' => Semester::factory(),
            'is_mandatory' => true,
        ];
    }
}
