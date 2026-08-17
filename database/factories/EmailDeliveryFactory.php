<?php

namespace Database\Factories;

use App\Models\EmailDelivery;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailDelivery>
 */
class EmailDeliveryFactory extends Factory
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
            'user_id' => User::factory(),
            'recipient_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(4),
            'mailer' => 'resend',
            'status' => 'queued',
            'idempotency_key' => 'email-delivery/'.fake()->uuid(),
            'metadata' => [],
        ];
    }
}
