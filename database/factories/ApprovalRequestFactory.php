<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
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
            'requester_user_id' => User::factory()->staff(),
            'type' => 'import_review',
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }
}
