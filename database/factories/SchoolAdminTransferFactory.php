<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolAdminTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SchoolAdminTransfer>
 */
class SchoolAdminTransferFactory extends Factory
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

        return [
            'school_id' => $school,
            'initiated_by_user_id' => User::factory()->schoolAdmin()->for($school),
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
