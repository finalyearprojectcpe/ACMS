<?php

namespace App\Services;

use App\AccountType;
use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardAccess
{
    public function user(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
                && in_array($user->account_type, [AccountType::Adviser, AccountType::Student], true),
            403,
        );

        return $user;
    }

    public function advisor(Request $request): AdvisorProfile
    {
        $user = $this->user($request);
        abort_unless(
            $user->isAdviser(),
            403,
        );

        return $user->advisorProfile->loadMissing('user');
    }

    public function student(Request $request): StudentProfile
    {
        $user = $this->user($request);
        abort_unless(
            $user->isStudent(),
            403,
        );

        return $user->studentProfile->loadMissing([
            'user',
            'advisorProfile.user',
            'studentSet.currentAcademicYear',
        ]);
    }

    public function advisorFor(User $user): AdvisorProfile
    {
        $advisor = $user->isAdviser()
            ? $user->advisorProfile
            : $user->studentProfile?->advisorProfile;
        abort_unless($advisor instanceof AdvisorProfile, 404);

        return $advisor->loadMissing('user');
    }

    public function studentSet(AdvisorProfile $advisor, int $studentSetId): StudentSet
    {
        return $advisor->studentSets()
            ->findOrFail($studentSetId);
    }

    public function studentSetByRoute(
        AdvisorProfile $advisor,
        string $departmentSlug,
        string $entrySession,
    ): StudentSet {
        abort_unless(preg_match('/^\d{8}$/', $entrySession) === 1, 404);

        return $advisor->studentSets()
            ->where('department_slug', $departmentSlug)
            ->where('entry_start_year', (int) substr($entrySession, 0, 4))
            ->where('entry_end_year', (int) substr($entrySession, 4, 4))
            ->firstOrFail();
    }

    public function academicSessionByYears(StudentSet $set, string $sessionYears): AcademicSession
    {
        abort_unless(preg_match('/^\d{8}$/', $sessionYears) === 1, 404);

        return $set->academicSessions()
            ->where('start_year', (int) substr($sessionYears, 0, 4))
            ->where('end_year', (int) substr($sessionYears, 4, 4))
            ->firstOrFail();
    }

    public function studentSetByFilterKey(AdvisorProfile $advisor, string $filterKey): StudentSet
    {
        abort_unless(preg_match('/^([a-z0-9-]+)-(\d{4})(\d{4})$/', $filterKey, $parts) === 1, 404);

        return $this->studentSetByRoute($advisor, $parts[1], $parts[2].$parts[3]);
    }

    public function semesterByRoute(StudentSet $set, string $sessionYears, string $semester): AcademicSessionSemester
    {
        abort_unless(in_array($semester, ['first', 'second'], true), 404);

        return $this->academicSessionByYears($set, $sessionYears)
            ->semesterInstances()
            ->whereHas('semester', fn ($query) => $query->where('slug', $semester))
            ->firstOrFail();
    }

    public function courseOfferingByRoute(StudentSet $set, AcademicSessionSemester $period, string $courseCode): CourseOffering
    {
        $code = Course::codeFromUrl($courseCode);
        abort_unless($code !== null, 404);

        return $period->courseOfferings()
            ->whereHas('course', fn ($query) => $query
                ->where('student_set_id', $set->id)
                ->where('code', $code))
            ->with('course')
            ->firstOrFail();
    }

    public function ensureOwnsRegistration(
        AdvisorProfile $advisor,
        CourseRegistration $registration,
    ): void {
        abort_unless(
            $registration->studentProfile()
                ->where('advisor_profile_id', $advisor->id)
                ->exists(),
            404,
        );
    }

    public function registrationByRoute(AdvisorProfile $advisor, string $registrationNumber, string $sessionYears, string $semester): CourseRegistration
    {
        abort_unless(preg_match('/^\d{8}$/', $sessionYears) === 1, 404);

        return CourseRegistration::query()
            ->whereHas('studentProfile', fn ($query) => $query
                ->where('advisor_profile_id', $advisor->id)
                ->where('registration_number', $registrationNumber))
            ->whereHas('academicSessionSemester', fn ($query) => $query
                ->whereHas('academicSession', fn ($sessions) => $sessions
                    ->where('start_year', (int) substr($sessionYears, 0, 4))
                    ->where('end_year', (int) substr($sessionYears, 4, 4)))
                ->whereHas('semester', fn ($semesters) => $semesters->where('slug', $semester)))
            ->firstOrFail();
    }

    public function ensureOwnsDue(AdvisorProfile $advisor, Due $due): void
    {
        abort_unless($due->advisor_profile_id === $advisor->id, 404);
    }

    public function ensureOwnsAssignment(
        AdvisorProfile $advisor,
        Due $due,
        StudentDueAssignment $assignment,
    ): void {
        $this->ensureOwnsDue($advisor, $due);
        abort_unless($assignment->due_id === $due->id, 404);
    }

    public function canViewPayment(User $user, DuePayment $payment): bool
    {
        return ($user->account_type === AccountType::Adviser
                && $user->advisorProfile?->id === $payment->advisor_profile_id)
            || ($user->account_type === AccountType::Student
                && $user->studentProfile?->id === $payment->student_profile_id);
    }
}
