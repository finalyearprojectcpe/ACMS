<?php

namespace Database\Factories;

use App\Models\StudentJoinRequest;
use App\Models\StudentSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentJoinRequest>
 */
class StudentJoinRequestFactory extends Factory
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
            'student_set_id' => StudentSet::factory(),
            'registration_number' => (string) fake()->numberBetween(2024000000, 2024999999),
            'status' => StudentJoinRequest::StatusPending,
        ];
    }
}
