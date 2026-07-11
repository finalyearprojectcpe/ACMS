<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicYearSemester;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\ResultSheet;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultSheet>
 */
class ResultSheetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $school = School::factory()->create();
        $faculty = Faculty::factory()->create(['school_id' => $school->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $entryAcademicSession = AcademicSession::factory()->create(['school_id' => $school->id]);
        $academicSession = AcademicSession::factory()->create(['school_id' => $school->id]);

        return [
            'school_id' => $school->id,
            'department_id' => $department->id,
            'entry_academic_session_id' => $entryAcademicSession->id,
            'academic_session_id' => $academicSession->id,
            'academic_year_semester_id' => AcademicYearSemester::factory(),
            'status' => ResultSheet::StatusDraft,
            'current_revision_number' => 0,
        ];
    }
}
