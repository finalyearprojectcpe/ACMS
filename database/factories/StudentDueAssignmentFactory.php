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
                'school_id' => $due->school_id,
                'department_id' => $due->department_id,
                'entry_academic_session_id' => $due->entry_academic_session_id,
            ])->id,
            'amount_kobo' => $due->amount_kobo,
            'status' => StudentDueAssignment::StatusPending,
            'paid_at' => null,
            'latest_due_payment_id' => null,
        ];
    }
}
