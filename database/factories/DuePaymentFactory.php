<?php

namespace Database\Factories;

use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DuePayment>
 */
class DuePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assignment = StudentDueAssignment::factory()->create();
        $studentProfile = $assignment->studentProfile;

        return [
            'student_due_assignment_id' => $assignment->id,
            'student_profile_id' => $studentProfile->id,
            'advisor_profile_id' => $studentProfile->advisor_profile_id,
            'payment_account_id' => null,
            'provider' => DuePayment::ProviderPaystack,
            'reference' => 'DUE-'.Str::upper(Str::random(24)),
            'status' => DuePayment::StatusInitialized,
            'amount_kobo' => $assignment->amount_kobo,
            'currency' => 'NGN',
            'authorization_url' => 'https://checkout.paystack.com/'.Str::random(12),
            'access_code' => 'ACCS_'.Str::random(16),
        ];
    }
}
