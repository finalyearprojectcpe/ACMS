<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\DepartmentLevelStaffAssignment;
use App\Models\Level;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentLevelStaffAssignment>
 */
class DepartmentLevelStaffAssignmentFactory extends Factory
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
            'level_id' => Level::factory(),
            'staff_profile_id' => StaffProfile::factory(),
        ];
    }
}
