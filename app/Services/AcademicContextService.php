<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\AdvisorProfile;
use App\Models\Semester;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class AcademicContextService
{
    public function currentSession(AdvisorProfile $advisorProfile, ?CarbonInterface $at = null): ?AcademicSession
    {
        $date = $this->localDate($advisorProfile, $at);

        return $advisorProfile->academicSessions()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->latest('starts_on')
            ->first();
    }

    public function currentSemesterInstance(AcademicSession $academicSession, ?CarbonInterface $at = null): ?AcademicSessionSemester
    {
        $academicSession->loadMissing('advisorProfile');
        $date = $this->localDate($academicSession->advisorProfile, $at);

        return $academicSession->semesterInstances()
            ->with('semester')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    public function currentAcademicYearSemester(StudentProfile $studentProfile): ?AcademicYearSemester
    {
        $studentProfile->loadMissing(['advisorProfile', 'studentSet.entryAcademicSession']);
        $academicSession = $this->currentSession($studentProfile->advisorProfile);
        $academicYear = $this->academicYearForSet($studentProfile->studentSet, $academicSession);
        $semester = $academicSession instanceof AcademicSession
            ? $this->currentSemesterInstance($academicSession)?->semester
            : null;

        if (! ($academicYear instanceof AcademicYear) || ! ($semester instanceof Semester)) {
            return null;
        }

        return AcademicYearSemester::query()
            ->whereBelongsTo($academicYear)
            ->whereBelongsTo($semester)
            ->first();
    }

    public function academicYearForSet(StudentSet $studentSet, ?AcademicSession $targetAcademicSession = null): ?AcademicYear
    {
        $studentSet->loadMissing(['advisorProfile', 'entryAcademicSession']);
        $targetAcademicSession ??= $this->currentSession($studentSet->advisorProfile);

        if (! ($targetAcademicSession instanceof AcademicSession)) {
            return null;
        }

        $sessions = $studentSet->advisorProfile->academicSessions()
            ->oldest('starts_on')
            ->get(['id'])
            ->values();
        $entryIndex = $sessions->search(fn (AcademicSession $session): bool => $session->is($studentSet->entryAcademicSession));
        $targetIndex = $sessions->search(fn (AcademicSession $session): bool => $session->is($targetAcademicSession));

        if ($entryIndex === false || $targetIndex === false || $targetIndex < $entryIndex) {
            return null;
        }

        $yearNumber = (int) $targetIndex - (int) $entryIndex + 1;

        if ($yearNumber > $studentSet->minimum_years_to_degree) {
            return null;
        }

        return AcademicYear::query()->where('year_number', $yearNumber)->first();
    }

    public function academicYearForStudent(StudentProfile $studentProfile, ?AcademicSession $targetAcademicSession = null): ?AcademicYear
    {
        $studentProfile->loadMissing(['studentSet.entryAcademicSession', 'currentAcademicYear']);

        return $studentProfile->studentSet instanceof StudentSet
            ? $this->academicYearForSet($studentProfile->studentSet, $targetAcademicSession)
            : ($targetAcademicSession === null ? $studentProfile->currentAcademicYear : null);
    }

    public function sessionForSetYear(StudentSet $studentSet, AcademicYear $academicYear): ?AcademicSession
    {
        $studentSet->loadMissing(['advisorProfile', 'entryAcademicSession']);
        $sessions = $studentSet->advisorProfile->academicSessions()->oldest('starts_on')->get()->values();
        $entryIndex = $sessions->search(fn (AcademicSession $session): bool => $session->is($studentSet->entryAcademicSession));

        if ($entryIndex === false) {
            return null;
        }

        $target = $sessions->get((int) $entryIndex + max($academicYear->year_number - 1, 0));

        return $target instanceof AcademicSession ? $target : null;
    }

    /**
     * @return array{year: AcademicYear, semester: Semester, slot: AcademicYearSemester, session: AcademicSession}
     */
    public function courseOfferingContext(
        StudentSet $studentSet,
        string $levelSlug,
        string $semesterSlug,
    ): array {
        $year = AcademicYear::query()
            ->where('slug', $levelSlug)
            ->where('year_number', '<=', $studentSet->minimum_years_to_degree)
            ->firstOrFail();
        $semester = Semester::query()->where('slug', $semesterSlug)->firstOrFail();
        $session = $this->sessionForSetYear($studentSet, $year);

        if (! $session instanceof AcademicSession) {
            throw ValidationException::withMessages([
                'session' => 'Create the academic session for this student set and level before continuing.',
            ]);
        }

        return [
            'year' => $year,
            'semester' => $semester,
            'slot' => $this->academicYearSemester($year, $semester),
            'session' => $session,
        ];
    }

    /** @return array{0: AcademicSession, 1: AcademicYearSemester}|null */
    public function registrationContext(StudentProfile $studentProfile): ?array
    {
        $session = $this->currentSession($studentProfile->advisorProfile)
            ?? $studentProfile->advisorProfile->academicSessions()->latest('starts_on')->first();
        $year = $session instanceof AcademicSession
            ? $this->academicYearForStudent($studentProfile, $session)
            : null;
        $semester = $session instanceof AcademicSession
            ? $this->currentSemesterInstance($session)?->semester
            : null;
        $semester ??= Semester::query()->orderBy('sort_order')->first();

        if (! ($session instanceof AcademicSession)
            || ! ($year instanceof AcademicYear)
            || ! ($semester instanceof Semester)) {
            return null;
        }

        return [
            $session,
            $this->academicYearSemester($year, $semester)
                ->loadMissing(['academicYear', 'semester']),
        ];
    }

    /** @return array{0: AcademicSession, 1: AcademicYearSemester} */
    public function requireRegistrationContext(StudentProfile $studentProfile): array
    {
        $context = $this->registrationContext($studentProfile);

        if ($context === null) {
            throw ValidationException::withMessages([
                'registration' => 'Course registration is not available until the current academic session and semester are configured.',
            ]);
        }

        return $context;
    }

    public function setHasGraduated(StudentSet $studentSet): bool
    {
        return $this->academicYearForSet($studentSet) === null
            && $this->currentSession($studentSet->advisorProfile) instanceof AcademicSession
            && $studentSet->entryAcademicSession->starts_on->isBefore($this->currentSession($studentSet->advisorProfile)->starts_on);
    }

    public function academicYearSemester(
        AcademicYear $academicYear,
        Semester $semester,
    ): AcademicYearSemester {
        return AcademicYearSemester::query()
            ->whereBelongsTo($academicYear)
            ->whereBelongsTo($semester)
            ->firstOrFail();
    }

    private function localDate(AdvisorProfile $advisorProfile, ?CarbonInterface $at): string
    {
        $timezone = $advisorProfile->timezone ?: config('app.timezone');
        $dateTime = $at instanceof CarbonInterface
            ? CarbonImmutable::instance($at)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        return $dateTime->toDateString();
    }
}
