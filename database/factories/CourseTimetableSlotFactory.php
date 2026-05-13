<?php

namespace Database\Factories;

use App\Models\CourseTimetableSlot;
use App\Models\CourseOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTimetableSlot>
 */
class CourseTimetableSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_offering_id' => CourseOffering::factory(),
            'day_of_week' => $this->faker->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'venue' => 'ETF 204',
        ];
    }
}
