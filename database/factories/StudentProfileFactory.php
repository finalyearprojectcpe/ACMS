<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $set = StudentSet::factory()->create();

        return [
            'user_id' => User::factory()->student(),
            'advisor_profile_id' => $set->advisor_profile_id,
            'student_set_id' => $set->id,
            'current_academic_year_id' => AcademicYear::query()->oldest('year_number')->first()?->id,
            'registration_number' => fake()->unique()->numerify('20########'),
            'status' => 'active',
        ];
    }
}
