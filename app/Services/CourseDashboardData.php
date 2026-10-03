<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
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
            ->with(['academicYear', 'semesterInstances.semester'])
            ->orderBy('start_year')
            ->get()
            ->map(fn (AcademicSession $session): array => [
                ...$this->sessionPayload($session),
                'isCurrent' => $set->current_semester_id !== null
                    && (int) $set->current_academic_year_id === (int) $session->academic_year_id,
                'currentSemesterId' => $set->current_semester_id !== null
                    && (int) $set->current_academic_year_id === (int) $session->academic_year_id
                    ? $set->current_semester_id : null,
                'semesters' => $session->semesterInstances->sortBy('semester.sort_order')
                    ->map(fn (AcademicSessionSemester $semester): array => [
                        'id' => (int) $semester->semester_id,
                        'name' => $semester->semester->name,
                        'slug' => $semester->semester->slug,
                    ])->values()->all(),
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
            'catalogCourses' => $offering instanceof CourseOffering ? [] : $set->courses()
                ->whereDoesntHave('offerings', fn ($query) => $query->where('academic_session_semester_id', $semester->id))
                ->orderBy('code')->get()
                ->map(fn (Course $course): array => [
                    'id' => (int) $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'units' => (int) $course->credit_units,
                    'isElective' => (bool) $course->is_elective,
                ])->all(),
            'catalogUrl' => route('student-sets.catalog.show', $set->routeParameters()),
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

    public function catalog(StudentSet $set, ?Course $editing = null): array
    {
        return [
            'set' => $this->presenter->studentSet($set),
            'courses' => $set->courses()->withCount('offerings')->orderBy('code')->get()
                ->map(fn (Course $course): array => [
                    'code' => $course->code,
                    'urlCode' => $course->urlCode(),
                    'title' => $course->title,
                    'units' => (int) $course->credit_units,
                    'isElective' => (bool) $course->is_elective,
                    'scheduledCount' => (int) $course->offerings_count,
                ])->all(),
            'editingCode' => $editing?->urlCode(),
        ];
    }

    public function assignment(StudentSet $set, StudentProfile $student, AcademicSessionSemester $period): array
    {
        $period->loadMissing(['academicSession.academicYear', 'semester']);
        $student->loadMissing('user');
        $registration = $student->courseRegistrations()->with('items.courseOffering')
            ->where('academic_session_semester_id', $period->id)->first();
        $plan = $this->registrationPlanner->plan($student, $period);

        return [
            'set' => $this->presenter->studentSet($set),
            'student' => [
                'name' => $student->official_name,
                'avatar' => $student->user?->profilePhotoUrl(),
                'registrationNumber' => $student->registration_number,
                'profileUrl' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
            ],
            'period' => [
                'level' => $period->academicSession->academicYear->name,
                'session' => $period->academicSession->label(),
                'semester' => $period->semester->name,
                'sessionYears' => $period->academicSession->routeYears(),
                'slug' => $period->semester->slug,
            ],
            'maximumUnits' => (int) $period->maximum_course_units,
            'status' => $registration?->status ?? 'unassigned',
            'selectedCourses' => $registration?->items->map(fn (CourseRegistrationItem $item): array => $this->registrationItem($item))->all() ?? [],
            'courseGroups' => $plan['courseGroups'],
        ];
    }

    public function studentAssignments(StudentProfile $student): array
    {
        $set = $student->studentSet;
        $sessions = $set->academicSessions()->with(['academicYear', 'semesterInstances.semester'])
            ->orderBy('start_year')->get();
        $semesterIds = $sessions->flatMap(fn (AcademicSession $session) => $session->semesterInstances->pluck('id'));
        $registrations = $student->courseRegistrations()
            ->whereIn('academic_session_semester_id', $semesterIds)
            ->withCount('items')->withSum('items', 'credit_units')
            ->get()->keyBy('academic_session_semester_id');

        return $sessions->flatMap(fn (AcademicSession $session) => $session->semesterInstances
            ->sortBy('semester.sort_order')->map(function (AcademicSessionSemester $semester) use ($set, $student, $session, $registrations): array {
                $registration = $registrations->get($semester->id);

                return [
                    'level' => $session->academicYear->name,
                    'session' => $session->label(),
                    'semester' => $semester->semester->name,
                    'status' => $registration?->status ?? 'unassigned',
                    'courseCount' => (int) ($registration?->items_count ?? 0),
                    'units' => (int) ($registration?->items_sum_credit_units ?? 0),
                    'maximumUnits' => (int) $semester->maximum_course_units,
                    'url' => route('student-sets.assignments.show', [
                        ...$set->routeParameters(),
                        'sessionYears' => $session->routeYears(),
                        'semester' => $semester->semester->slug,
                        'registrationNumber' => $student->routeRegistrationNumber(),
                        'from' => 'profile',
                    ]),
                ];
            }))->values()->all();
    }

    public function studentHome(StudentProfile $student): array
    {
        $semester = $this->academicContext->currentSemesterForStudent($student);
        $registration = $semester === null ? null : CourseRegistration::query()
            ->with('items.courseOffering')
            ->where('student_profile_id', $student->id)
            ->where('academic_session_semester_id', $semester->id)
            ->first();
        $courses = $registration?->status === CourseRegistration::StatusAssigned ? $registration->items : collect();
        $student->loadMissing(['user', 'studentSet', 'advisorProfile.user']);

        return [
            'student' => [
                'name' => $student->user->name,
                'avatar' => $student->user->profilePhotoUrl(),
                'registrationNumber' => $student->registration_number,
                'department' => $student->studentSet->department_name,
                'entrySet' => $student->studentSet->label(),
            ],
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
                'assignmentOrigin' => $item->assignment_origin,
                'isElective' => (bool) $item->is_elective,
            ])->all(),
        ];
    }

    public function studentCourses(StudentProfile $student, ?string $requestedSession, ?string $requestedSemester): array
    {
        abort_unless(($requestedSession === null) === ($requestedSemester === null), 404);
        $sessions = $student->studentSet->academicSessions()
            ->with(['academicYear', 'semesterInstances.semester'])
            ->orderBy('start_year')->get();
        $selectedSession = $requestedSession === null
            ? $sessions->firstWhere('academic_year_id', $student->studentSet->current_academic_year_id)
            : $sessions->first(fn (AcademicSession $session): bool => $session->routeYears() === $requestedSession);
        abort_if($requestedSession !== null && $selectedSession === null, 404);
        $selectedSemester = $selectedSession?->semesterInstances->first(
            fn (AcademicSessionSemester $item): bool => $item->semester->slug === ($requestedSemester
                ?? $student->studentSet->currentSemester?->slug),
        );
        abort_if($requestedSemester !== null && $selectedSemester === null, 404);
        $selectedSemester ??= $selectedSession?->semesterInstances->sortBy('semester.sort_order')->first();
        $navigation = $sessions->map(fn (AcademicSession $session): array => [
            'id' => (int) $session->id,
            'publicId' => $session->routeYears(),
            'name' => $session->label(),
            'level' => $this->presenter->level($session->academicYear),
            'semesters' => $session->semesterInstances->sortBy('semester.sort_order')
                ->map(fn (AcademicSessionSemester $item): array => [
                    'id' => (int) $item->id,
                    'publicId' => $item->semester->slug,
                    'name' => $item->semester->name,
                    'slug' => $item->semester->slug,
                ])->values()->all(),
        ])->all();

        if (! $selectedSemester instanceof AcademicSessionSemester) {
            return [
                'currentSemester' => null,
                'selectedSemesterId' => null,
                'sessions' => $navigation,
                'selectedCourses' => [],
            ];
        }

        $semester = $selectedSemester;
        $registration = CourseRegistration::query()
            ->with('items.courseOffering')
            ->where('student_profile_id', $student->id)
            ->where('academic_session_semester_id', $semester->id)
            ->first();

        return [
            'currentSemester' => [
                'name' => $semester->semester->name,
                'level' => $semester->academicSession->academicYear->name,
                'session' => $semester->academicSession->label(),
                'sessionYears' => $semester->academicSession->routeYears(),
                'slug' => $semester->semester->slug,
            ],
            'selectedSemesterId' => (int) $semester->id,
            'sessions' => $navigation,
            'selectedCourses' => ($registration?->status === CourseRegistration::StatusAssigned ? $registration->items : collect())
                ->map(fn (CourseRegistrationItem $item): array => $this->registrationItem($item))->all(),
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
                    'resultsPublishedAt' => $semester->courseOfferings->first(fn (CourseOffering $offering): bool => $offering->results_published_at !== null)?->results_published_at?->toIso8601String(),
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
        $set = $semester->academicSession->studentSet;
        $registrations = CourseRegistration::query()
            ->where('academic_session_semester_id', $semester->id)
            ->where('status', CourseRegistration::StatusAssigned)
            ->whereHas('studentProfile', fn ($query) => $query->where('student_set_id', $set->id))
            ->with('items')->get()->keyBy('student_profile_id');
        $offeringItems = $registrations->flatMap(fn (CourseRegistration $registration) => $registration->items)
            ->groupBy('course_offering_id');

        return [
            'id' => (int) $semester->id,
            'publicId' => $semester->public_id,
            'name' => $semester->semester->name,
            'slug' => $semester->semester->slug,
            'session' => $this->sessionPayload($semester->academicSession),
            'maximumCourseUnits' => (int) $semester->maximum_course_units,
            'resultsPublishedAt' => $offerings->first(fn (CourseOffering $offering): bool => $offering->results_published_at !== null)?->results_published_at?->toIso8601String(),
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
                'retakeOnly' => (bool) $offering->is_retake_only,
                'assignedCount' => $offeringItems->get($offering->id, collect())->count(),
                'enteredCount' => $offeringItems->get($offering->id, collect())
                    ->filter(fn (CourseRegistrationItem $item): bool => $item->grade !== null || $item->missing_script)->count(),
                'publishedAt' => $offering->results_published_at?->toIso8601String(),
                'gradeUrl' => route('student-sets.results.course', [
                    ...$set->routeParameters(),
                    'sessionYears' => $semester->academicSession->routeYears(),
                    'semester' => $semester->semester->slug,
                    'courseCode' => $offering->course->urlCode(),
                ]),
                'prerequisites' => $offering->prerequisiteCourses->map(fn (Course $course): array => [
                    'id' => (int) $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                ])->all(),
            ])->all(),
            'students' => $set->students()->with('user')->orderBy('official_name')->get()
                ->map(function (StudentProfile $student) use ($registrations, $set, $semester): array {
                    $registration = $registrations->get($student->id);

                    return [
                        'name' => $student->official_name,
                        'avatar' => $student->user?->profilePhotoUrl(),
                        'registrationNumber' => $student->registration_number,
                        'courseCount' => $registration?->items->count() ?? 0,
                        'units' => (int) ($registration?->items->sum('credit_units') ?? 0),
                        'assignmentUrl' => route('student-sets.assignments.show', [
                            ...$set->routeParameters(),
                            'sessionYears' => $semester->academicSession->routeYears(),
                            'semester' => $semester->semester->slug,
                            'registrationNumber' => $student->routeRegistrationNumber(),
                        ]),
                        'profileUrl' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
                    ];
                })->all(),
        ];
    }

    private function registrationItem(CourseRegistrationItem $item): array
    {
        return [
            'courseOfferingId' => (int) $item->course_offering_id,
            'courseId' => (int) $item->courseOffering->course_id,
            'assignmentOrigin' => $item->assignment_origin,
            'code' => $item->course_code,
            'title' => $item->course_title,
            'units' => (int) $item->credit_units,
            'category' => $item->is_elective ? 'Elective Course' : 'Normal Course',
            'categoryValue' => $item->assignment_origin !== 'scheduled'
                ? $item->assignment_origin : ($item->is_elective ? 'elective' : 'normal'),
            'isElective' => (bool) $item->is_elective,
            'prerequisites' => [],
            'missingPrerequisites' => [],
        ];
    }
}
