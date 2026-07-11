<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\Department;
use App\Models\School;
use App\Models\Semester;
use App\Models\StudentProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AcademicContextService
{
    public function currentSession(School $school, ?CarbonInterface $at = null): ?AcademicSession
    {
        $date = $this->localDate($school, $at);

        return $school->academicSessions()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->latest('starts_on')
            ->first();
    }

    public function currentSemesterInstance(
        AcademicSession $academicSession,
        ?CarbonInterface $at = null,
    ): ?AcademicSessionSemester {
        $academicSession->loadMissing('school');
        $date = $this->localDate($academicSession->school, $at);

        return $academicSession->semesterInstances()
            ->with('semester')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    public function currentAcademicYearSemester(StudentProfile $studentProfile): ?AcademicYearSemester
    {
        $studentProfile->loadMissing(['department.faculty.school', 'entryAcademicSession']);
        $department = $studentProfile->department;
        $school = $department?->faculty?->school;

        if (! $department instanceof Department || ! $school instanceof School) {
            return null;
        }

        $academicSession = $this->currentSession($school);

        if (! $academicSession instanceof AcademicSession) {
            return null;
        }

        $academicYear = $this->academicYearForSet($department, $studentProfile->entryAcademicSession, $academicSession);
        $semester = $this->currentSemesterInstance($academicSession)?->semester;

        if (! $academicYear instanceof AcademicYear || ! $semester instanceof Semester) {
            return null;
        }

        return AcademicYearSemester::query()
            ->whereBelongsTo($academicYear)
            ->whereBelongsTo($semester)
            ->first();
    }

    public function academicYearForSet(
        Department $department,
        ?AcademicSession $entryAcademicSession,
        ?AcademicSession $targetAcademicSession = null,
    ): ?AcademicYear {
        if (! $entryAcademicSession instanceof AcademicSession) {
            return null;
        }

        $department->loadMissing('faculty.school');
        $school = $department->faculty?->school;

        if (! $school instanceof School) {
            return null;
        }

        $targetAcademicSession ??= $this->currentSession($school);

        if (! $targetAcademicSession instanceof AcademicSession) {
            return null;
        }

        $sessions = $school->academicSessions()
            ->oldest('starts_on')
            ->get(['id'])
            ->values();
        $entryIndex = $sessions->search(
            fn (AcademicSession $academicSession): bool => $academicSession->is($entryAcademicSession),
        );
        $targetIndex = $sessions->search(
            fn (AcademicSession $academicSession): bool => $academicSession->is($targetAcademicSession),
        );

        if ($entryIndex === false || $targetIndex === false || $targetIndex < $entryIndex) {
            return null;
        }

        $yearNumber = (int) $targetIndex - (int) $entryIndex + 1;

        if ($yearNumber > (int) $department->minimum_years_to_degree) {
            return null;
        }

        return AcademicYear::query()
            ->where('year_number', $yearNumber)
            ->first();
    }

    public function academicYearForStudent(
        StudentProfile $studentProfile,
        ?AcademicSession $targetAcademicSession = null,
    ): ?AcademicYear {
        $studentProfile->loadMissing(['department', 'entryAcademicSession', 'currentAcademicYear']);

        if (! $studentProfile->department instanceof Department) {
            return null;
        }

        if ($studentProfile->entryAcademicSession instanceof AcademicSession) {
            return $this->academicYearForSet(
                $studentProfile->department,
                $studentProfile->entryAcademicSession,
                $targetAcademicSession,
            );
        }

        return $targetAcademicSession === null ? $studentProfile->currentAcademicYear : null;
    }

    public function sessionForSetYear(
        Department $department,
        ?AcademicSession $entryAcademicSession,
        AcademicYear $academicYear,
    ): ?AcademicSession {
        if (! $entryAcademicSession instanceof AcademicSession) {
            return null;
        }

        $department->loadMissing('faculty.school');
        $school = $department->faculty?->school;

        if (! $school instanceof School) {
            return null;
        }

        $sessions = $school->academicSessions()
            ->oldest('starts_on')
            ->get()
            ->values();
        $entryIndex = $sessions->search(
            fn (AcademicSession $academicSession): bool => $academicSession->is($entryAcademicSession),
        );

        if ($entryIndex === false) {
            return null;
        }

        $target = $sessions->get((int) $entryIndex + max($academicYear->year_number - 1, 0));

        return $target instanceof AcademicSession ? $target : null;
    }

    public function setHasGraduated(Department $department, ?AcademicSession $entryAcademicSession): bool
    {
        if (! $entryAcademicSession instanceof AcademicSession) {
            return false;
        }

        $department->loadMissing('faculty.school');
        $school = $department->faculty?->school;
        $currentSession = $school instanceof School ? $this->currentSession($school) : null;

        if (! $currentSession instanceof AcademicSession) {
            return false;
        }

        $sessions = $school->academicSessions()->oldest('starts_on')->pluck('id')->values();
        $entryIndex = $sessions->search($entryAcademicSession->id);
        $currentIndex = $sessions->search($currentSession->id);

        return $entryIndex !== false
            && $currentIndex !== false
            && ((int) $currentIndex - (int) $entryIndex + 1) > (int) $department->minimum_years_to_degree;
    }

    private function localDate(School $school, ?CarbonInterface $at): string
    {
        $timezone = $school->timezone ?: config('app.timezone');
        $dateTime = $at instanceof CarbonInterface
            ? CarbonImmutable::instance($at)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        return $dateTime->toDateString();
    }
}
