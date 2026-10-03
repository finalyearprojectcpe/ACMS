<?php

namespace Database\Factories;

use App\Models\StudentJoinRequest;
use App\Models\StudentProfile;
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
            'student_profile_id' => StudentProfile::factory()->unclaimed(),
            'status' => StudentJoinRequest::StatusPending,
        ];
    }
}
