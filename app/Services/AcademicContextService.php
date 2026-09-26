<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Validation\ValidationException;

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

    public function sessionForSetYear(StudentSet $studentSet, AcademicYear $academicYear): ?AcademicSession
    {
        return $studentSet->academicSessions()
            ->whereBelongsTo($academicYear)
            ->first();
    }

    public function registrationContext(StudentProfile $studentProfile): ?AcademicSessionSemester
    {
        return AcademicSessionSemester::query()
            ->with(['academicSession.academicYear', 'semester'])
            ->where('registration_enabled', true)
            ->whereHas('academicSession', fn ($query) => $query
                ->where('student_set_id', $studentProfile->student_set_id))
            ->latest('id')
            ->first();
    }

    public function requireRegistrationContext(StudentProfile $studentProfile): AcademicSessionSemester
    {
        $context = $this->registrationContext($studentProfile);

        if (! $context instanceof AcademicSessionSemester) {
            throw ValidationException::withMessages([
                'registration' => 'Course registration is not open for your set.',
            ]);
        }

        return $context;
    }
}
