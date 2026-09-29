<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResultDashboardData
{
    public function __construct(
        private readonly DashboardPresenter $presenter,
        private readonly ResultService $results,
    ) {}

    public function adviserOverview(StudentSet $set, ?AcademicSessionSemester $selected = null): array
    {
        $sessions = $this->sessions($set);
        $current = $sessions->firstWhere('academic_year_id', $set->current_academic_year_id) ?? $sessions->last();
        $selected ??= $current?->semesterInstances->firstWhere('registration_enabled', true)
            ?? $current?->semesterInstances->sortByDesc('semester.sort_order')->first();

        $courses = [];
        $period = null;

        if ($selected instanceof AcademicSessionSemester) {
            $selected->loadMissing(['academicSession.academicYear', 'semester', 'courseOfferings.course']);
            $counts = CourseRegistrationItem::query()
                ->selectRaw('course_offering_id, count(*) as registered_count, sum(case when grade is not null or missing_script = 1 then 1 else 0 end) as entered_count')
                ->whereHas('courseRegistration', fn (Builder $query) => $query
                    ->where('academic_session_semester_id', $selected->id)
                    ->where('status', CourseRegistration::StatusApproved)
                    ->whereHas('studentProfile', fn (Builder $students) => $students->where('student_set_id', $set->id)))
                ->groupBy('course_offering_id')
                ->get()
                ->keyBy('course_offering_id');
            $courses = $selected->courseOfferings->sortBy('course.code')->map(function (CourseOffering $offering) use ($counts, $set, $selected): array {
                $count = $counts->get($offering->id);

                return [
                    'publicId' => $offering->public_id,
                    'code' => $offering->course->code,
                    'title' => $offering->course_title,
                    'units' => (int) $offering->credit_units,
                    'isElective' => (bool) $offering->is_elective,
                    'registeredCount' => (int) ($count?->registered_count ?? 0),
                    'enteredCount' => (int) ($count?->entered_count ?? 0),
                    'publishedAt' => $offering->results_published_at?->toIso8601String(),
                    'url' => route('student-sets.results.course', [
                        ...$set->routeParameters(),
                        'sessionYears' => $selected->academicSession->routeYears(),
                        'semester' => $selected->semester->slug,
                        'courseCode' => $offering->course->urlCode(),
                    ]),
                ];
            })->values()->all();
            $period = $this->period($selected) + [
                'registrationEnabled' => (bool) $selected->registration_enabled,
                'scheduleUrl' => route('student-sets.courses.show', [
                    ...$set->routeParameters(),
                    'session' => $selected->academicSession->routeYears(),
                    'semester' => $selected->semester->slug,
                ]),
                'registrationsUrl' => route('student-sets.registrations.show', [
                    ...$set->routeParameters(),
                    'session' => $selected->academicSession->routeYears(),
                    'semester' => $selected->semester->slug,
                    'status' => 'submitted',
                ]),
            ];
        }

        return [
            'set' => $this->presenter->studentSet($set),
            'sessions' => $sessions->map(fn (AcademicSession $session): array => $this->session($session))->all(),
            'selectedSemesterId' => $selected?->id,
            'period' => $period,
            'courses' => $courses,
        ];
    }

    public function courseRoster(StudentSet $set, AcademicSessionSemester $semester, CourseOffering $offering): array
    {
        $semester->loadMissing(['academicSession.academicYear', 'semester']);
        $offering->loadMissing('course');
        $students = $this->results->roster($semester, $offering);
        $corrections = DB::table('grade_corrections')
            ->whereIn('course_registration_item_id', $students->pluck('id'))
            ->orderBy('created_at')
            ->get()
            ->groupBy('course_registration_item_id');

        return [
            'set' => $this->presenter->studentSet($set),
            'period' => $this->period($semester),
            'publication' => $this->results->publicationState($semester, $offering),
            'publishedAt' => $offering->results_published_at?->toIso8601String(),
            'course' => [
                'code' => $offering->course->code,
                'title' => $offering->course_title,
                'units' => (int) $offering->credit_units,
                'isElective' => (bool) $offering->is_elective,
            ],
            'students' => $students->map(fn (CourseRegistrationItem $item): array => [
                'itemId' => (int) $item->id,
                'name' => $item->courseRegistration->studentProfile->user->name,
                'avatar' => $item->courseRegistration->studentProfile->user->profilePhotoUrl(),
                'registrationNumber' => $item->courseRegistration->studentProfile->registration_number,
                'profileUrl' => route('dashboard.students.show', [
                    'registrationNumber' => $item->courseRegistration->studentProfile->routeRegistrationNumber(),
                ]),
                'grade' => $item->grade,
                'missingScript' => $item->missing_script,
                'corrections' => $corrections->get($item->id, collect())->map(fn ($change): array => [
                    'from' => $change->old_missing_script ? 'Missing script' : ($change->old_grade ?? 'No result'),
                    'to' => $change->new_missing_script ? 'Missing script' : $change->new_grade,
                    'reason' => $change->reason,
                    'at' => $change->created_at,
                ])->all(),
            ])->all(),
            'saveUrl' => route('student-sets.results.course.save', [
                ...$set->routeParameters(),
                'sessionYears' => $semester->academicSession->routeYears(),
                'semester' => $semester->semester->slug,
                'courseCode' => $offering->course->urlCode(),
            ]),
            'publishUrl' => route('student-sets.results.publish', [
                ...$set->routeParameters(),
                'sessionYears' => $semester->academicSession->routeYears(),
                'semester' => $semester->semester->slug,
                'courseCode' => $offering->course->urlCode(),
            ]),
            'semesterResultsUrl' => route('student-sets.results.semester', [
                ...$set->routeParameters(),
                'sessionYears' => $semester->academicSession->routeYears(),
                'semester' => $semester->semester->slug,
            ]),
        ];
    }

    public function studentResults(StudentProfile $student, ?string $requestedSession, ?string $requestedSemester, bool $forAdviser): array
    {
        abort_unless(($requestedSession === null) === ($requestedSemester === null), 404);
        $summaries = $this->results->studentPeriods($this->results->studentRegistrations($student))
            ->filter(fn (array $summary): bool => $summary['visibleItems']->isNotEmpty());
        $periods = $summaries->map(function (array $summary) use ($student, $forAdviser): array {
            $registration = $summary['registration'];
            $semester = $registration->academicSessionSemester;
            $session = $semester->academicSession;
            $route = $forAdviser ? 'dashboard.students.results.semester' : 'dashboard.section';
            $parameters = $forAdviser
                ? [
                    'registrationNumber' => $student->routeRegistrationNumber(),
                    'sessionYears' => $session->routeYears(),
                    'semester' => $semester->semester->slug,
                ]
                : ['section' => 'results'];

            return [
                'sessionYears' => $session->routeYears(),
                'semester' => $semester->semester->slug,
                'label' => $session->academicYear->name.' · '.$session->label().' Session · '.$semester->semester->name,
                'shortLabel' => $session->academicYear->year_number.'00L '.($semester->semester->sort_order === 1 ? '1st' : '2nd'),
                'gpa' => $summary['gpa'],
                'cgpa' => $summary['cgpa'],
                'url' => route($route, $forAdviser ? $parameters : $parameters + [
                    'session' => $session->routeYears(),
                    'semester' => $semester->semester->slug,
                ]),
                'registration' => $registration,
            ];
        })->values();
        $history = $periods->map(function (array $period): array {
            $registration = $period['registration'];
            $semester = $registration->academicSessionSemester;

            return [
                'sessionYears' => $period['sessionYears'],
                'session' => $semester->academicSession->label(),
                'level' => $semester->academicSession->academicYear->name,
                'semester' => $semester->semester->name,
                'gpa' => $period['gpa'],
                'cgpa' => $period['cgpa'],
                'url' => $period['url'],
            ];
        });
        $selected = $requestedSession === null
            ? ($forAdviser ? $periods->last() : null)
            : $periods->first(fn (array $period): bool => $period['sessionYears'] === $requestedSession && $period['semester'] === $requestedSemester);
        abort_if($requestedSession !== null && $selected === null, 404);

        return [
            'periods' => $periods->map(fn (array $period): array => collect($period)->except('registration')->all())->all(),
            'history' => $history->all(),
            'selected' => $selected === null ? null : [
                'sessionYears' => $selected['sessionYears'],
                'semester' => $selected['semester'],
                'label' => $selected['label'],
                'gpa' => $selected['gpa'],
                'courses' => $selected['registration']->items
                    ->sortBy('course_code')
                    ->map(fn (CourseRegistrationItem $item): array => [
                        'code' => $item->course_code,
                        'title' => $item->course_title,
                        'units' => (int) $item->credit_units,
                        'grade' => $item->courseOffering?->results_published_at !== null ? $item->grade : null,
                        'missingScript' => $item->courseOffering?->results_published_at !== null && $item->missing_script,
                        'pending' => $item->courseOffering?->results_published_at === null,
                    ])->values()->all(),
            ],
        ];
    }

    public function studentOverview(StudentProfile $student): array
    {
        $sessions = $this->sessions($student->studentSet)->sortBy('start_year')->values();
        $summaries = $this->results->studentPeriods($this->results->studentRegistrations($student));
        $bySemester = $summaries->keyBy(fn (array $summary): int => $summary['registration']->academic_session_semester_id);
        $columns = $sessions->map(function (AcademicSession $session) use ($student, $bySemester): array {
            $sessionSummaries = $bySemester->filter(fn (array $summary): bool => $summary['registration']->academicSessionSemester->academic_session_id === $session->id);
            $semesters = $session->semesterInstances->sortBy('semester.sort_order')->map(function (AcademicSessionSemester $semester) use ($student, $bySemester, $session): array {
                $summary = $bySemester->get($semester->id);
                $hasResults = is_array($summary) && $summary['visibleItems']->isNotEmpty();

                return [
                    'name' => $semester->semester->name,
                    'gpa' => $hasResults ? $summary['gpa'] : null,
                    'url' => $hasResults ? route('dashboard.students.results.semester', [
                        'registrationNumber' => $student->routeRegistrationNumber(),
                        'sessionYears' => $session->routeYears(),
                        'semester' => $semester->semester->slug,
                    ]) : null,
                ];
            })->values();

            return [
                'level' => $session->academicYear->name,
                'session' => $session->label(),
                'semesters' => $semesters->all(),
                'cgpa' => $sessionSummaries->contains(fn (array $summary): bool => $summary['counted'])
                    ? $sessionSummaries->last()['cgpa'] : null,
            ];
        })->all();

        return [
            'cgpa' => $summaries->last()['cgpa'] ?? null,
            'columns' => $columns,
        ];
    }

    public function pendingReviews(StudentProfile $student): array
    {
        return $student->courseRegistrations()
            ->where('status', CourseRegistration::StatusSubmitted)
            ->with(['academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->get()
            ->map(fn (CourseRegistration $registration): array => [
                'label' => $registration->academicSessionSemester->academicSession->academicYear->name.' '
                    .$registration->academicSessionSemester->semester->name,
                'url' => route('dashboard.course-registration.show', $registration->reviewRouteParameters()),
            ])->all();
    }

    /** @return Collection<int, AcademicSession> */
    private function sessions(StudentSet $set): Collection
    {
        return $set->academicSessions()
            ->with(['academicYear', 'semesterInstances.semester', 'semesterInstances.courseOfferings'])
            ->orderBy('start_year')
            ->get();
    }

    private function session(AcademicSession $session): array
    {
        return [
            ...$this->presenter->academicSession($session),
            'level' => $this->presenter->level($session->academicYear),
            'semesters' => $session->semesterInstances->sortBy('semester.sort_order')
                ->map(fn (AcademicSessionSemester $semester): array => [
                    'id' => (int) $semester->id,
                    'publicId' => $semester->public_id,
                    'name' => $semester->semester->name,
                    'slug' => $semester->semester->slug,
                    'registrationEnabled' => (bool) $semester->registration_enabled,
                    'courseCount' => $semester->courseOfferings->count(),
                    'resultsPublishedAt' => $semester->courseOfferings->first(fn (CourseOffering $offering): bool => $offering->results_published_at !== null)?->results_published_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    private function period(AcademicSessionSemester $semester): array
    {
        return [
            'level' => $semester->academicSession->academicYear->name,
            'session' => $semester->academicSession->label(),
            'semester' => $semester->semester->name,
        ];
    }
}
