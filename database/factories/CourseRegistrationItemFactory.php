<?php

namespace Database\Factories;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseRegistrationItem>
 */
class CourseRegistrationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $offering = CourseOffering::factory()->create();
        $offering->load('course');

        return [
            'course_registration_id' => CourseRegistration::factory(),
            'course_offering_id' => $offering->id,
            'category' => 'normal',
            'course_code' => $offering->course->code,
            'course_title' => $offering->course->title,
            'credit_units' => $offering->course->credit_units,
        ];
    }
}
