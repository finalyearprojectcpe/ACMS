<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicYearSemester;
use App\Models\CourseRegistration;
use App\Models\StudentProfile;
use App\Models\StudentSet;
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
        $set = StudentSet::factory()->create();
        $studentProfile = StudentProfile::factory()->create([
            'advisor_profile_id' => $set->advisor_profile_id,
            'student_set_id' => $set->id,
        ]);

        return [
            'advisor_profile_id' => $set->advisor_profile_id,
            'student_set_id' => $set->id,
            'student_profile_id' => $studentProfile->id,
            'academic_session_id' => AcademicSession::factory()->create([
                'advisor_profile_id' => $set->advisor_profile_id,
            ])->id,
            'academic_year_semester_id' => AcademicYearSemester::factory(),
            'reviewer_advisor_profile_id' => null,
            'status' => CourseRegistration::StatusDraft,
            'feedback_note' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
        ];
    }
}
