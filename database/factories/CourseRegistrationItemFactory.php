<?php

namespace Database\Factories;

use App\Models\Course;
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
        $registration = CourseRegistration::factory()->create();
        $registration->load('studentProfile.studentSet');
        $offering = CourseOffering::factory()->create([
            'course_id' => Course::factory()->create([
                'student_set_id' => $registration->studentProfile->student_set_id,
            ])->id,
            'academic_session_semester_id' => $registration->academic_session_semester_id,
        ]);
        $offering->load('course');

        return [
            'course_registration_id' => $registration->id,
            'course_offering_id' => $offering->id,
            'is_elective' => false,
            'course_code' => $offering->course->code,
            'course_title' => $offering->course->title,
            'credit_units' => $offering->credit_units,
        ];
    }
}
