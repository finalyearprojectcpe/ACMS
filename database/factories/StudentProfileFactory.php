<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\School;
use App\Models\StudentProfile;
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
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory()->student(),
            'department_id' => Department::factory(),
            'current_academic_year_id' => AcademicYear::factory(),
            'entry_academic_session_id' => AcademicSession::factory(),
            'registration_number' => fake()->unique()->numerify('20########'),
        ];
    }
}
