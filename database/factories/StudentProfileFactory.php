<?php

namespace Database\Factories;

use App\Models\AdvisorProfile;
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
        return [
            'user_id' => User::factory()->student(),
            'advisor_profile_id' => AdvisorProfile::factory(),
            'student_set_id' => fn (array $attributes): int => StudentSet::factory()->create([
                'advisor_profile_id' => $attributes['advisor_profile_id'],
            ])->id,
            'registration_number' => fake()->unique()->numerify('20########'),
            'status' => 'active',
        ];
    }
}
