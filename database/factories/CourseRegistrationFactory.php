<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicYearSemester;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\School;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseRegistration>
 */
class CourseRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $school = School::factory()->create();
        $department = Department::factory()->create();

        return [
            'school_id' => $school->id,
            'department_id' => $department->id,
            'student_profile_id' => StudentProfile::factory()->create([
                'school_id' => $school->id,
                'department_id' => $department->id,
            ])->id,
            'academic_session_id' => AcademicSession::factory()->create([
                'school_id' => $school->id,
            ])->id,
            'academic_year_semester_id' => AcademicYearSemester::factory(),
            'status' => CourseRegistration::StatusDraft,
            'feedback_note' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
        ];
    }
}
