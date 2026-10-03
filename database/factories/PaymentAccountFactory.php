<?php

namespace Database\Factories;

use App\Models\AdvisorProfile;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'advisor_profile_id' => AdvisorProfile::factory(),
            'provider' => PaymentAccount::ProviderPaystack,
            'label' => 'Student Dues Account',
            'business_name' => 'ACMS Student Dues',
            'bank_code' => '058',
            'bank_name' => 'Guaranty Trust Bank',
            'account_number' => '0123456789',
            'account_number_last4' => '6789',
            'account_name' => 'Okoro Chinedu',
            'paystack_subaccount_code' => 'ACCT_'.strtoupper(fake()->bothify('??????????')),
            'status' => PaymentAccount::StatusActive,
            'verified_at' => now(),
            'activated_at' => now(),
            'provider_payload' => [
                'subaccount_code' => 'demo',
            ],
        ];
    }
}
