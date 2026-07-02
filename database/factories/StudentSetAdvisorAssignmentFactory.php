<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\StudentSetAdvisorAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSetAdvisorAssignment>
 */
class StudentSetAdvisorAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'entry_academic_session_id' => AcademicSession::factory(),
            'staff_profile_id' => StaffProfile::factory(),
            'assigned_by_staff_profile_id' => null,
            'starts_academic_session_id' => null,
            'ends_academic_session_id' => null,
            'active_marker' => 1,
        ];
    }
}
