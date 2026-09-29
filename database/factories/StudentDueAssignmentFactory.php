<?php

namespace Database\Factories;

use App\Models\Due;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentDueAssignment>
 */
class StudentDueAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $due = Due::factory()->create();

        return [
            'due_id' => $due->id,
            'student_profile_id' => StudentProfile::factory()->create([
                'advisor_profile_id' => $due->advisor_profile_id,
                'student_set_id' => $due->student_set_id,
            ])->id,
            'amount_kobo' => $due->amount_kobo,
            'status' => StudentDueAssignment::StatusPending,
            'paid_at' => null,
            'latest_due_payment_id' => null,
        ];
    }
}
