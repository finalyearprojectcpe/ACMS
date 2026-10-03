<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
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
            'student_profile_id' => $studentProfile->id,
            'academic_session_semester_id' => AcademicSessionSemester::factory()->create([
                'academic_session_id' => AcademicSession::factory()->create([
                    'student_set_id' => $set->id,
                ])->id,
            ])->id,
            'status' => CourseRegistration::StatusDraft,
            'assigned_by_user_id' => null,
            'assigned_at' => null,
        ];
    }
}
