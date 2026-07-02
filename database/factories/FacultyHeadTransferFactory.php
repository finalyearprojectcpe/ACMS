<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\FacultyHeadTransfer;
use App\Models\School;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FacultyHeadTransfer>
 */
class FacultyHeadTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);
        $school = School::factory();
        $faculty = Faculty::factory()->for($school);
        $department = Department::factory()->for($faculty);
        $recipient = User::factory()->staff()->for($school);

        return [
            'school_id' => $school,
            'faculty_id' => $faculty,
            'initiated_by_user_id' => User::factory()->staff()->for($school),
            'recipient_user_id' => $recipient,
            'recipient_staff_profile_id' => StaffProfile::factory()
                ->for($recipient, 'user')
                ->for($department),
            'accepted_by_user_id' => null,
            'recipient_email' => fake()->unique()->safeEmail(),
            'recipient_name' => fake()->name(),
            'access_code' => Str::upper(fake()->bothify('????####')),
            'token_hash' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'cancelled_at' => null,
        ];
    }
}
