<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\PaymentAccount;
use App\Models\School;
use App\Models\StaffProfile;
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
        $school = School::factory()->create();
        $department = Department::factory()->create([
            'faculty_id' => Faculty::factory()->create(['school_id' => $school->id])->id,
        ]);
        $staffProfile = StaffProfile::factory()->create([
            'department_id' => $department->id,
        ]);

        return [
            'school_id' => $school->id,
            'department_id' => $department->id,
            'staff_profile_id' => $staffProfile->id,
            'entry_academic_session_id' => null,
            'provider' => PaymentAccount::ProviderPaystack,
            'label' => 'Student Dues Account',
            'business_name' => 'GradeManager Department Dues',
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
