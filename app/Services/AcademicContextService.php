<?php

namespace App\Services;

use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\StudentProfile;
use App\Models\StudentSet;

class AcademicContextService
{
    public function academicYearForSet(StudentSet $studentSet): ?AcademicYear
    {
        return $studentSet->currentAcademicYear;
    }

    public function academicYearForStudent(StudentProfile $studentProfile): ?AcademicYear
    {
        return $this->academicYearForSet($studentProfile->studentSet);
    }

    public function currentSemesterForStudent(StudentProfile $studentProfile): ?AcademicSessionSemester
    {
        $set = $studentProfile->studentSet;

        if ($set->current_academic_year_id === null || $set->current_semester_id === null) {
            return null;
        }

        return $set->academicSessions()
            ->where('academic_year_id', $set->current_academic_year_id)
            ->first()?->semesterInstances()
            ->with(['academicSession.academicYear', 'semester'])
            ->where('semester_id', $set->current_semester_id)
            ->first();
    }
}
