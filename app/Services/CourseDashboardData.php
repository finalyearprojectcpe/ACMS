<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Due;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CourseDashboardData
{
    public function __construct(
        private readonly AcademicContextService $academicContext,
        private readonly CourseRegistrationPlanner $registrationPlanner,
        private readonly DashboardPresenter $presenter,
    ) {}

    public function offerings(Request $request, Collection $sets): array
    {
        $selectedSet = $sets->first(fn (StudentSet $set): bool => $set->filterKey() === $request->query('set'));
        abort_if($request->query('set') !== null && $selectedSet === null, 404);
        $selectedSet ??= $sets->first();

        if (! $selectedSet instanceof StudentSet) {
            return [
                'selectedSet' => null,
                'sessions' => [],
                'selectedSessionId' => null,
                'selectedSemester' => null,
            ];
        }

        $sessions = $selectedSet->academicSessions()
            ->with([
                'academicYear',
                'semesterInstances.semester',
                'semesterInstances.courseOfferings.course',
                'semesterInstances.courseOfferings.prerequisiteCourses',
            ])
            ->orderBy('start_year')
            ->get();
        $selectedSession = $sessions->first(fn (AcademicSession $session): bool => $session->routeYears() === $request->query('session'))
            ?? $sessions->firstWhere('academic_year_id', $selectedSet->current_academic_year_id)
            ?? $sessions->last();
        $selectedSemester = $selectedSession?->semesterInstances
            ->first(fn (AcademicSessionSemester $semester): bool => $semester->semester->slug === $request->query('semester'))
            ?? $selectedSession?->semesterInstances->sortBy('semester.sort_order')->first();

        return [
            'selectedSet' => $this->presenter->studentSet($selectedSet),
            'sessions' => $sessions->map(fn (AcademicSession $session): array => $this->sessionSummary($session))->all(),
            'selectedSessionId' => $selectedSession?->id,
            'selectedSemester' => $selectedSemester instanceof AcademicSessionSemester
                ? $this->semesterSchedule($selectedSemester)
                : null,
        ];
    }

    public function sessionForm(StudentSet $set, ?AcademicSession $session = null): array
    {
        $session?->loadMissing('semesterInstances.semester');
        $sessions = $set->academicSessions()
            ->with('academicYear')
            ->orderBy('start_year')
            ->get();
        $latestEndYear = $sessions->max('end_year');

        return [
            'mode' => $session instanceof AcademicSession ? 'edit' : 'create',
            'set' => $this->presenter->studentSet($set),
            'levels' => AcademicYear::query()
                ->orderBy('year_number')
                ->get()
                ->map(function (AcademicYear $year) use ($sessions, $session): array {
                    $usedBy = $sessions->firstWhere('academic_year_id', $year->id);
                    $available = ! $usedBy instanceof AcademicSession
                        || (int) $usedBy->id === (int) $session?->id;

                    return [
                        ...$this->presenter->level($year),
                        'available' => $available,
                        'unavailableReason' => $available
                            ? null
                            : "Already used by {$usedBy->label()} Session",
                    ];
                })
                ->all(),
            'startYear' => $session?->start_year
                ?? ($latestEndYear === null ? (int) $set->entry_start_year : (int) $latestEndYear),
            'session' => $session instanceof AcademicSession ? [
                'id' => (int) $session->id,
                'startYear' => (int) $session->start_year,
                'endYear' => (int) $session->end_year,
                'academicYearId' => (int) $session->academic_year_id,
                'hasDependents' => $session->hasAcademicRecords(),
            ] : null,
            'semesterMaximumCourseUnits' => [
                'first' => (int) ($session?->semesterInstances
                    ->first(fn (AcademicSessionSemester $semester): bool => $semester->semester->slug === 'first')
                    ?->maximum_course_units ?? 24),
                'second' => (int) ($session?->semesterInstances
                    ->first(fn (AcademicSessionSemester $semester): bool => $semester->semester->slug === 'second')
                    ?->maximum_course_units ?? 24),
            ],
        ];
    }

    public function settingsSessions(StudentSet $set): array
    {
        return $set->academicSessions()
            ->with('academicYear')
            ->orderBy('start_year')
            ->get()
            ->map(fn (AcademicSession $session): array => [
                ...$this->sessionPayload($session),
                'isCurrent' => (int) $set->current_academic_year_id === (int) $session->academic_year_id,
                'hasDependents' => $session->hasAcademicRecords(),
            ])->all();
    }

    public function offeringForm(
        StudentSet $set,
        AcademicSessionSemester $semester,
        ?CourseOffering $offering = null,
    ): array {
        $semester->loadMissing(['academicSession.academicYear', 'semester']);
        $previous = CourseOffering::query()
            ->with(['course', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->availableAsPrerequisiteFor($set, $semester)
            ->get()
            ->unique('course_id')
            ->sortBy(fn (CourseOffering $item): string => sprintf(
                '%04d-%02d-%s',
                $item->academicSessionSemester->academicSession->start_year,
                $item->academicSessionSemester->semester->sort_order,
                $item->course->code,
            ))
            ->values();
        $offering?->loadMissing(['course', 'prerequisiteCourses']);

        return [
            'mode' => $offering instanceof CourseOffering ? 'edit' : 'create',
            'set' => $this->presenter->studentSet($set),
            'academicSession' => $this->sessionPayload($semester->academicSession),
            'level' => $this->presenter->level($semester->academicSession->academicYear),
            'semester' => [
                'id' => (int) $semester->id,
                'publicId' => $semester->public_id,
                'name' => $semester->semester->name,
                'slug' => $semester->semester->slug,
            ],
            'offering' => $offering instanceof CourseOffering ? [
                'id' => (int) $offering->id,
                'publicId' => $offering->public_id,
                'code' => $offering->course->code,
                'urlCode' => $offering->course->urlCode(),
                'title' => $offering->course_title,
                'creditUnits' => (int) $offering->credit_units,
                'category' => $offering->is_elective ? 'elective' : 'normal',
                'prerequisiteCourseIds' => $offering->prerequisiteCourses
                    ->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ] : null,
            'prerequisiteOptions' => $previous->map(fn (CourseOffering $item): array => [
                'id' => (int) $item->course_id,
                'code' => $item->course->code,
                'title' => $item->course_title,
                'units' => (int) $item->credit_units,
                'level' => $item->academicSessionSemester->academicSession->academicYear->name,
                'semester' => $item->academicSessionSemester->semester->name,
            ])->all(),
        ];
    }

    public function studentHome(StudentProfile $student): array
    {
        $semester = $this->academicContext->registrationContext($student);
        $registration = $semester === null ? null : CourseRegistration::query()
            ->with('items')
            ->where('student_profile_id', $student->id)
            ->where('academic_session_semester_id', $semester->id)
            ->first();
        $courses = $registration?->items ?? collect();
        $pendingDues = $student->dueAssignments()
            ->with('due')
            ->where('status', StudentDueAssignment::StatusPending)
            ->whereHas('due', fn ($query) => $query->where('status', Due::StatusPublished))
            ->get();

        return [
            'currentSemester' => $semester === null ? null : [
                'name' => $semester->semester->name,
                'level' => $semester->academicSession->academicYear->name,
                'session' => $semester->academicSession->label(),
            ],
            'courseAdvisor' => $student->advisorProfile->user->displayName(),
            'totalCourseUnits' => (int) $courses->sum('credit_units'),
            'courses' => $courses->map(fn (CourseRegistrationItem $item): array => [
                'code' => $item->course_code,
                'title' => $item->course_title,
                'units' => (int) $item->credit_units,
                'type' => $item->is_elective ? 'Elective Course' : 'Normal Course',
            ])->all(),
            'pendingDues' => $pendingDues->map(fn (StudentDueAssignment $assignment): array => [
                'title' => $assignment->due->title,
                'context' => $student->studentSet->label(),
                'amount' => $this->presenter->amount((int) $assignment->amount_kobo),
                'dueDate' => $assignment->due->due_on?->toFormattedDateString() ?? 'No due date',
            ])->all(),
        ];
    }

    public function studentRegistration(StudentProfile $student): array
    {
        $semester = $this->academicContext->registrationContext($student);
        $historyQuery = $student->courseRegistrations()
            ->with(['items', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->latest('submitted_at');

        if (! $semester instanceof AcademicSessionSemester) {
            return [
                'available' => false,
                'unavailableReason' => 'Your course adviser has not opened a semester for course registration.',
                'currentSemester' => null,
                'maximumCourseUnits' => 0,
                'advisorName' => $student->advisorProfile->user->displayName(),
                'reviewStatus' => 'Not submitted',
                'feedbackNote' => null,
                'registrationId' => null,
                'selectedUnits' => 0,
                'selectedCourses' => [],
                'availableCourses' => [],
                'selectableCourses' => [],
                'courseGroups' => [],
                'previousRegistrations' => $historyQuery->get()
                    ->map(fn (CourseRegistration $item): array => $this->registrationSummary($item, false))->all(),
            ];
        }

        $plan = $this->registrationPlanner->plan($student, $semester);
        $registration = CourseRegistration::query()
            ->with('items')
            ->where('student_profile_id', $student->id)
            ->where('academic_session_semester_id', $semester->id)
            ->first();
        $history = $historyQuery
            ->when($registration instanceof CourseRegistration, fn ($query) => $query->whereKeyNot($registration->id))
            ->get();

        return [
            'available' => true,
            'unavailableReason' => null,
            'currentSemester' => [
                'name' => $semester->semester->name,
                'level' => $semester->academicSession->academicYear->name,
                'session' => $semester->academicSession->label(),
            ],
            'maximumCourseUnits' => (int) $semester->maximum_course_units,
            'advisorName' => $student->advisorProfile->user->displayName(),
            'reviewStatus' => $registration === null ? 'Not submitted' : $this->statusLabel($registration->status),
            'feedbackNote' => $registration?->feedback_note,
            'registrationId' => $registration?->id,
            'selectedUnits' => (int) ($registration?->items->sum('credit_units') ?? 0),
            'selectedCourses' => $registration?->items
                ->map(fn (CourseRegistrationItem $item): array => $this->registrationItem($item))->all() ?? [],
            'availableCourses' => $plan['availableCourses'],
            'selectableCourses' => $plan['selectableCourses'],
            'courseGroups' => $plan['courseGroups'],
            'previousRegistrations' => $history
                ->map(fn (CourseRegistration $item): array => $this->registrationSummary($item, false))->all(),
        ];
    }

    public function adviserRegistrations(
        AdvisorProfile $advisor,
        ?CourseRegistration $review = null,
        ?StudentSet $selectedSet = null,
        ?string $selectedSessionPublicId = null,
        ?string $selectedSemesterPublicId = null,
        string $status = 'submitted',
    ): array {
        $status = in_array($status, ['submitted', 'approved', 'returned', 'all'], true) ? $status : 'submitted';
        $sets = $advisor->studentSets()->orderByDesc('entry_start_year')->get();
        $selectedSet ??= $sets->first();
        $sessions = $selectedSet instanceof StudentSet
            ? $selectedSet->academicSessions()
                ->with([
                    'academicYear',
                    'semesterInstances.semester',
                    'semesterInstances.courseOfferings',
                ])
                ->orderBy('start_year')
                ->get()
            : collect();
        $selectedSession = $sessions->firstWhere('public_id', $selectedSessionPublicId)
            ?? $sessions->firstWhere('academic_year_id', $selectedSet?->current_academic_year_id)
            ?? $sessions->last();
        $selectedSemester = $selectedSession?->semesterInstances
            ->firstWhere('public_id', $selectedSemesterPublicId)
            ?? $selectedSession?->semesterInstances->sortBy('semester.sort_order')->first();
        $registrations = CourseRegistration::query()
            ->with([
                'items',
                'studentProfile.user',
                'studentProfile.studentSet',
                'academicSessionSemester.academicSession.academicYear',
                'academicSessionSemester.semester',
            ])
            ->whereHas('studentProfile', fn ($query) => $query
                ->where('advisor_profile_id', $advisor->id)
                ->when($selectedSet instanceof StudentSet, fn ($query) => $query
                    ->where('student_set_id', $selectedSet->id)))
            ->when(
                $selectedSemester instanceof AcademicSessionSemester,
                fn ($query) => $query->where('academic_session_semester_id', $selectedSemester->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', '!=', CourseRegistration::StatusDraft)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('submitted_at')
            ->get();

        return [
            'selectedSet' => $selectedSet instanceof StudentSet ? $this->presenter->studentSet($selectedSet) : null,
            'sessions' => $sessions->map(fn (AcademicSession $session): array => $this->sessionSummary($session))->all(),
            'selectedSessionId' => $selectedSession?->id,
            'selectedSemesterId' => $selectedSemester?->id,
            'status' => $status,
            'registrations' => $registrations
                ->map(fn (CourseRegistration $item): array => $this->registrationSummary($item, true))->all(),
            'review' => $review instanceof CourseRegistration ? $this->registrationReview($review) : null,
        ];
    }

    private function sessionSummary(AcademicSession $session): array
    {
        return [
            ...$this->sessionPayload($session),
            'semesters' => $session->semesterInstances
                ->sortBy('semester.sort_order')
                ->map(fn (AcademicSessionSemester $semester): array => [
                    'id' => (int) $semester->id,
                    'publicId' => $semester->public_id,
                    'name' => $semester->semester->name,
                    'slug' => $semester->semester->slug,
                    'maximumCourseUnits' => (int) $semester->maximum_course_units,
                    'registrationEnabled' => (bool) $semester->registration_enabled,
                    'resultsPublishedAt' => $semester->results_published_at?->toIso8601String(),
                    'courseCount' => $semester->courseOfferings->count(),
                ])->values()->all(),
        ];
    }

    private function sessionPayload(AcademicSession $session): array
    {
        $session->loadMissing(['academicYear', 'studentSet']);

        return [
            ...$this->presenter->academicSession($session),
            'level' => $this->presenter->level($session->academicYear),
        ];
    }

    private function semesterSchedule(AcademicSessionSemester $semester): array
    {
        $semester->loadMissing([
            'semester',
            'academicSession.academicYear',
            'courseOfferings.course',
            'courseOfferings.prerequisiteCourses',
        ]);
        $offerings = $semester->courseOfferings
            ->sortBy('course.code')
            ->values();

        return [
            'id' => (int) $semester->id,
            'publicId' => $semester->public_id,
            'name' => $semester->semester->name,
            'slug' => $semester->semester->slug,
            'session' => $this->sessionPayload($semester->academicSession),
            'maximumCourseUnits' => (int) $semester->maximum_course_units,
            'registrationEnabled' => (bool) $semester->registration_enabled,
            'resultsPublishedAt' => $semester->results_published_at?->toIso8601String(),
            'totalUnits' => (int) $offerings->sum('credit_units'),
            'offerings' => $offerings->map(fn (CourseOffering $offering): array => [
                'id' => (int) $offering->id,
                'publicId' => $offering->public_id,
                'courseId' => (int) $offering->course_id,
                'code' => $offering->course->code,
                'urlCode' => $offering->course->urlCode(),
                'title' => $offering->course_title,
                'units' => (int) $offering->credit_units,
                'category' => $offering->is_elective ? 'elective' : 'normal',
                'prerequisites' => $offering->prerequisiteCourses->map(fn (Course $course): array => [
                    'id' => (int) $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                ])->all(),
            ])->all(),
        ];
    }

    private function registrationSummary(CourseRegistration $registration, bool $includeStudent): array
    {
        $registration->loadMissing([
            'items',
            'studentProfile.user',
            'academicSessionSemester.academicSession.academicYear',
            'academicSessionSemester.semester',
        ]);
        $semester = $registration->academicSessionSemester;
        $payload = [
            'id' => (int) $registration->id,
            'status' => $this->statusLabel($registration->status),
            'semester' => [
                'level' => $semester->academicSession->academicYear->name,
                'name' => $semester->semester->name,
                'session' => $semester->academicSession->label(),
            ],
            'totalUnits' => (int) $registration->items->sum('credit_units'),
            'courses' => $registration->items
                ->map(fn (CourseRegistrationItem $item): array => $this->registrationItem($item))->all(),
            'reviewUrl' => route('dashboard.course-registration.show', $registration->reviewRouteParameters()),
        ];

        if ($includeStudent) {
            $payload['student'] = [
                'name' => $registration->studentProfile->user->name,
                'avatar' => $registration->studentProfile->user->profilePhotoUrl(),
                'registrationNumber' => $registration->studentProfile->registration_number,
                'profileUrl' => route('dashboard.students.show', [
                    'registrationNumber' => $registration->studentProfile->registration_number,
                ]),
            ];
            $payload['submittedAt'] = $registration->submitted_at?->toFormattedDateString();
        }

        return $payload;
    }

    private function registrationReview(CourseRegistration $registration): array
    {
        $registration->loadMissing(['studentProfile.studentSet', 'items']);

        return [
            ...$this->registrationSummary($registration, true),
            'routeParameters' => $registration->reviewRouteParameters(),
            'set' => $this->presenter->studentSet($registration->studentProfile->studentSet),
            'feedbackNote' => $registration->feedback_note,
            'canReview' => $registration->status === CourseRegistration::StatusSubmitted,
        ];
    }

    private function registrationItem(CourseRegistrationItem $item): array
    {
        return [
            'courseOfferingId' => (int) $item->course_offering_id,
            'code' => $item->course_code,
            'title' => $item->course_title,
            'units' => (int) $item->credit_units,
            'category' => $item->is_elective ? 'Elective Course' : 'Normal Course',
            'categoryValue' => $item->is_elective ? 'elective' : 'normal',
            'isElective' => (bool) $item->is_elective,
            'prerequisites' => [],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            CourseRegistration::StatusSubmitted => 'In review',
            CourseRegistration::StatusApproved => 'Approved',
            CourseRegistration::StatusReturned => 'Changes requested',
            default => 'Draft',
        };
    }
}
