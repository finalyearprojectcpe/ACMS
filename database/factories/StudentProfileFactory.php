<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Level;
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
            'user_id' => User::factory()->student(),
            'department_id' => Department::factory(),
            'current_level_id' => Level::factory(),
            'registration_number' => fake()->unique()->numerify('20########'),
        ];
    }
}
