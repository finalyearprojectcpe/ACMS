<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Due;
use App\Models\StudentSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Due>
 */
class DueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $set = StudentSet::factory()->create();
        $academicSession = AcademicSession::factory()->create([
            'student_set_id' => $set->id,
        ]);

        return [
            'advisor_profile_id' => $set->advisor_profile_id,
            'student_set_id' => $set->id,
            'academic_session_id' => $academicSession->id,
            'payment_account_id' => null,
            'title' => 'Student Dues',
            'description' => 'Set levy.',
            'amount_kobo' => 1500000,
            'currency' => 'NGN',
            'due_on' => now()->addMonth()->toDateString(),
            'status' => Due::StatusPublished,
            'published_at' => now(),
        ];
    }
}
