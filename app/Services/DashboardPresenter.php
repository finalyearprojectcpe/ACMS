<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\DuePayment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardPresenter
{
    public function __construct(
        private readonly AcademicContextService $academicContext,
        private readonly AccessCodeGenerator $accessCodes,
    ) {}

    public function adviserHome(AdvisorProfile $advisor, Collection $sets, Collection $levels): array
    {
        $students = $advisor->students()
            ->with(['user', 'currentLevel', 'studentSet.entryAcademicSession'])
            ->get();
        $payments = DuePayment::query()
            ->with(['studentProfile.user', 'assignment.due'])
            ->where('advisor_profile_id', $advisor->id)
            ->where('status', DuePayment::StatusSuccess)
            ->latest('paid_at')
            ->limit(8)
            ->get();

        return [
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSet($set))->values()->all(),
            'levels' => $levels->map(fn (AcademicYear $level): array => $this->level($level))->all(),
            'students' => $students->map(fn (StudentProfile $student): array => $this->adviserStudent($student))->all(),
            'totalStudents' => $students->count(),
            'maximumCourseUnits' => (int) $advisor->maximum_course_units,
            'pendingDuesByStudent' => [],
            'recentPayments' => $payments->map(fn (DuePayment $payment): array => [
                'studentName' => $payment->studentProfile->user->name,
                'studentAvatar' => $payment->studentProfile->user->profilePhotoUrl(),
                'dueName' => $payment->assignment->due->title,
                'amount' => $this->amount((int) $payment->amount_kobo),
                'paidAt' => $payment->paid_at?->toFormattedDateString() ?? 'Paid',
            ])->all(),
        ];
    }

    public function adviserStudents(AdvisorProfile $advisor, Collection $sets): array
    {
        $students = $advisor->students()
            ->with(['user', 'currentLevel', 'studentSet.entryAcademicSession'])
            ->get()
            ->sortBy(fn (StudentProfile $student): string => $student->user->name)
            ->values();

        return [
            'programme' => $this->programme($advisor),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSet($set))->values()->all(),
            'totalStudents' => $students->count(),
            'students' => $students->map(fn (StudentProfile $student): array => $this->adviserStudent($student))->all(),
        ];
    }

    public function studentForm(AdvisorProfile $advisor, string $mode): array
    {
        $sets = $advisor->studentSets()
            ->with('entryAcademicSession')
            ->orderByDesc('entry_academic_session_id')
            ->get();

        return [
            'mode' => $mode,
            'programme' => $this->programme($advisor),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSet($set))->values()->all(),
            'suggestedAccessCode' => $this->accessCodes->generate(),
        ];
    }

    public function adviserStudent(StudentProfile $student): array
    {
        $student->loadMissing([
            'user',
            'currentLevel',
            'studentSet.entryAcademicSession',
            'advisorProfile.user',
        ]);

        return [
            'id' => (int) $student->id,
            'fullName' => $student->user->name,
            'email' => $student->user->email,
            'registrationNumber' => $student->registration_number,
            'avatar' => $student->user->profilePhotoUrl(),
            'currentLevel' => $student->currentLevel instanceof AcademicYear
                ? $this->level($student->currentLevel)
                : null,
            'studentSet' => $this->studentSet($student->studentSet),
            'entrySession' => $this->academicSession($student->studentSet->entryAcademicSession),
            'advisorName' => $student->advisorProfile->user->name,
            'profileUrl' => route('dashboard.students.show', [
                'registrationNumber' => $student->registration_number,
            ]),
            'accountStatus' => $student->user->activated_at === null ? 'Pending signup' : 'Active',
            'accessCode' => $student->user->activated_at === null ? $student->user->access_code : null,
        ];
    }

    public function adviserSettings(
        Request $request,
        AdvisorProfile $advisor,
        Collection $sets,
    ): array {
        $sessions = $advisor->academicSessions()->orderByDesc('starts_on')->get();
        $setup = $request->string('setup')->toString();
        $editId = $request->integer('edit');
        $setupForm = null;

        if ($setup === 'academic-session') {
            $session = $editId > 0 ? $advisor->academicSessions()->findOrFail($editId) : null;
            $setupForm = [
                'kind' => 'academic-session',
                'mode' => $session instanceof AcademicSession ? 'edit' : 'create',
                'id' => $session?->id,
                'name' => $session?->name ?? '',
                'startsOn' => $session?->starts_on->toDateString() ?? '',
                'endsOn' => $session?->ends_on->toDateString() ?? '',
            ];
        }

        if ($setup === 'student-set') {
            $studentSet = $editId > 0
                ? $advisor->studentSets()->with('entryAcademicSession')->findOrFail($editId)
                : null;
            $setupForm = [
                'kind' => 'student-set',
                'mode' => $studentSet instanceof StudentSet ? 'edit' : 'create',
                'id' => $studentSet?->id,
                'entryAcademicSessionId' => $studentSet?->entry_academic_session_id,
                'minimumYearsToDegree' => $studentSet?->minimum_years_to_degree ?? $advisor->minimum_years_to_degree,
                'maximumCourseUnits' => $studentSet?->maximum_course_units ?? $advisor->maximum_course_units,
            ];
        }

        return [
            'adviser' => [
                'name' => $advisor->user->name,
                'email' => $advisor->user->email,
                'avatar' => $advisor->user->profilePhotoUrl(),
            ],
            'institution' => $advisor->institution_name,
            'programme' => $this->programme($advisor),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSet($set))->all(),
            'academicSessions' => $sessions->map(fn (AcademicSession $session): array => $this->academicSession($session))->all(),
            'setupForm' => $setupForm,
        ];
    }

    public function studentProfile(StudentProfile $student): array
    {
        [$surname, $otherNames] = $this->splitName($student->user->name);

        return [
            'name' => [
                'full' => $student->user->name,
                'surname' => $surname,
                'otherNames' => $otherNames,
            ],
            'email' => (string) $student->user->email,
            'avatar' => $student->user->profilePhotoUrl(),
            'registrationNumber' => $student->registration_number,
            'institution' => $student->advisorProfile->institution_name,
            'programme' => $this->programme($student->advisorProfile),
            'currentLevel' => $student->currentLevel instanceof AcademicYear
                ? $this->level($student->currentLevel)
                : null,
            'courseAdvisor' => $student->advisorProfile->user->name,
        ];
    }

    public function studentRecord(StudentProfile $student): array
    {
        return [
            'student' => $this->adviserStudent($student),
            'institution' => $student->advisorProfile->institution_name,
            'programme' => $this->programme($student->advisorProfile),
            'entrySession' => $this->academicSession($student->studentSet->entryAcademicSession),
            'courseAdvisor' => $student->advisorProfile->user->name,
        ];
    }

    public function studentHeader(StudentProfile $student): array
    {
        return [
            'registrationNumber' => $student->registration_number,
            'currentLevel' => $student->currentLevel instanceof AcademicYear
                ? $this->level($student->currentLevel)
                : null,
        ];
    }

    public function studentSet(StudentSet $set): array
    {
        $set->loadMissing(['entryAcademicSession', 'advisorProfile.user']);
        $currentLevel = $this->academicContext->academicYearForSet($set);

        return [
            'id' => (int) $set->id,
            'name' => $set->name,
            'entrySession' => $this->academicSession($set->entryAcademicSession),
            'currentLevel' => $currentLevel instanceof AcademicYear ? $this->level($currentLevel) : null,
            'advisorName' => $set->advisorProfile->user->name,
            'minimumYearsToDegree' => (int) $set->minimum_years_to_degree,
            'maximumCourseUnits' => (int) $set->maximum_course_units,
            'isGraduated' => $this->academicContext->setHasGraduated($set),
        ];
    }

    public function academicSession(AcademicSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'name' => $session->name,
            'startsOn' => $session->starts_on->toDateString(),
            'endsOn' => $session->ends_on->toDateString(),
            'status' => today()->betweenIncluded($session->starts_on, $session->ends_on)
                ? 'Current'
                : ($session->starts_on->isFuture() ? 'Upcoming' : 'Past'),
        ];
    }

    public function level(AcademicYear $level): array
    {
        return [
            'id' => (int) $level->id,
            'title' => $level->name,
            'slug' => $level->slug,
            'yearNumber' => (int) $level->year_number,
        ];
    }

    public function programme(AdvisorProfile $advisor): array
    {
        return [
            'id' => (int) $advisor->id,
            'name' => $advisor->programme_name,
            'abbreviation' => $advisor->programme_abbreviation,
            'minimumYearsToDegree' => (int) $advisor->minimum_years_to_degree,
        ];
    }

    public function levelsFor(AdvisorProfile $advisor): Collection
    {
        return AcademicYear::query()
            ->where('year_number', '<=', $advisor->minimum_years_to_degree)
            ->orderBy('year_number')
            ->get();
    }

    public function amount(int $amountKobo): string
    {
        return 'NGN '.number_format($amountKobo / 100, 2);
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
