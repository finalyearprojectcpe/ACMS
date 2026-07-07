<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Due;
use App\Models\Faculty;
use App\Models\School;
use App\Models\StaffProfile;
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
        $school = School::factory()->create();
        $department = Department::factory()->create([
            'faculty_id' => Faculty::factory()->create(['school_id' => $school->id])->id,
        ]);
        $entrySession = AcademicSession::factory()->create(['school_id' => $school->id]);
        $academicSession = AcademicSession::factory()->create(['school_id' => $school->id]);

        return [
            'school_id' => $school->id,
            'department_id' => $department->id,
            'entry_academic_session_id' => $entrySession->id,
            'academic_session_id' => $academicSession->id,
            'created_by_staff_profile_id' => StaffProfile::factory()->create(['department_id' => $department->id])->id,
            'title' => 'Departmental Dues',
            'description' => 'Department levy.',
            'amount_kobo' => 1500000,
            'currency' => 'NGN',
            'due_on' => now()->addMonth()->toDateString(),
            'status' => Due::StatusPublished,
            'published_at' => now(),
        ];
    }
}
