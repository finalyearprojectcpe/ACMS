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
                ->selectRaw('course_offering_id, count(*) as registered_count, sum(case when grade is not null then 1 else 0 end) as graded_count')
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
                    'gradedCount' => (int) ($count?->graded_count ?? 0),
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
                'publication' => $this->results->publicationState($selected),
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
                'publishUrl' => route('student-sets.results.publish', [
                    ...$set->routeParameters(),
                    'sessionYears' => $selected->academicSession->routeYears(),
                    'semester' => $selected->semester->slug,
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
                    'registrationNumber' => $item->courseRegistration->studentProfile->registration_number,
                ]),
                'grade' => $item->grade,
                'corrections' => $corrections->get($item->id, collect())->map(fn ($change): array => [
                    'from' => $change->old_grade,
                    'to' => $change->new_grade,
                    'at' => $change->created_at,
                ])->all(),
            ])->all(),
            'saveUrl' => route('student-sets.results.course.save', [
                ...$set->routeParameters(),
                'sessionYears' => $semester->academicSession->routeYears(),
                'semester' => $semester->semester->slug,
                'courseCode' => $offering->course->urlCode(),
            ]),
            'backUrl' => route('student-sets.results.semester', [
                ...$set->routeParameters(),
                'sessionYears' => $semester->academicSession->routeYears(),
                'semester' => $semester->semester->slug,
            ]),
        ];
    }

    public function studentResults(StudentProfile $student, ?string $requestedSession, ?string $requestedSemester, bool $forAdviser): array
    {
        abort_unless(($requestedSession === null) === ($requestedSemester === null), 404);
        $registrations = $student->courseRegistrations()
            ->where('status', CourseRegistration::StatusApproved)
            ->whereHas('academicSessionSemester', fn (Builder $query) => $query->whereNotNull('results_published_at'))
            ->with(['items', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->get()
            ->sortBy(fn (CourseRegistration $registration): string => sprintf(
                '%04d-%02d',
                $registration->academicSessionSemester->academicSession->start_year,
                $registration->academicSessionSemester->semester->sort_order,
            ))
            ->values();
        $periods = $registrations->map(function (CourseRegistration $registration) use ($student, $forAdviser): array {
            $semester = $registration->academicSessionSemester;
            $session = $semester->academicSession;
            $route = $forAdviser ? 'dashboard.students.results.semester' : 'dashboard.section';
            $parameters = $forAdviser
                ? [
                    'registrationNumber' => $student->registration_number,
                    'sessionYears' => $session->routeYears(),
                    'semester' => $semester->semester->slug,
                ]
                : ['section' => 'results'];

            return [
                'sessionYears' => $session->routeYears(),
                'semester' => $semester->semester->slug,
                'label' => $session->academicYear->name.' · '.$session->label().' Session · '.$semester->semester->name,
                'shortLabel' => $session->academicYear->year_number.'00L '.($semester->semester->sort_order === 1 ? '1st' : '2nd'),
                'gpa' => $this->results->gpa($registration->items),
                'url' => route($route, $forAdviser ? $parameters : $parameters + [
                    'session' => $session->routeYears(),
                    'semester' => $semester->semester->slug,
                ]),
                'registration' => $registration,
            ];
        });
        $selected = $requestedSession === null
            ? $periods->last()
            : $periods->first(fn (array $period): bool => $period['sessionYears'] === $requestedSession && $period['semester'] === $requestedSemester);
        abort_if($requestedSession !== null && $selected === null, 404);

        return [
            'periods' => $periods->map(fn (array $period): array => collect($period)->except('registration')->all())->all(),
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
                        'grade' => $item->grade,
                    ])->values()->all(),
            ],
        ];
    }

    public function studentOverview(StudentProfile $student): array
    {
        $sessions = $this->sessions($student->studentSet)->sortBy('start_year')->values();
        $registrations = $student->courseRegistrations()
            ->where('status', CourseRegistration::StatusApproved)
            ->whereHas('academicSessionSemester', fn (Builder $query) => $query->whereNotNull('results_published_at'))
            ->with('items')
            ->get()
            ->keyBy('academic_session_semester_id');
        $cumulativeItems = collect();
        $columns = $sessions->map(function (AcademicSession $session) use ($student, $registrations, $cumulativeItems): array {
            $semesters = $session->semesterInstances->sortBy('semester.sort_order')->map(function (AcademicSessionSemester $semester) use ($student, $registrations, $cumulativeItems, $session): array {
                $registration = $registrations->get($semester->id);
                if ($registration instanceof CourseRegistration) {
                    foreach ($registration->items as $item) {
                        $cumulativeItems->push($item);
                    }
                }

                return [
                    'name' => $semester->semester->name,
                    'gpa' => $registration instanceof CourseRegistration ? $this->results->gpa($registration->items) : null,
                    'url' => $registration instanceof CourseRegistration ? route('dashboard.students.results.semester', [
                        'registrationNumber' => $student->registration_number,
                        'sessionYears' => $session->routeYears(),
                        'semester' => $semester->semester->slug,
                    ]) : null,
                ];
            })->values();

            return [
                'level' => $session->academicYear->name,
                'session' => $session->label(),
                'semesters' => $semesters->all(),
                'cgpa' => $semesters->contains(fn (array $semester): bool => $semester['gpa'] !== null)
                    ? $this->results->gpa($cumulativeItems)
                    : null,
            ];
        })->all();

        return [
            'cgpa' => $this->results->gpa($cumulativeItems),
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
                    'resultsPublishedAt' => $semester->results_published_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    private function period(AcademicSessionSemester $semester): array
    {
        return [
            'level' => $semester->academicSession->academicYear->name,
            'session' => $semester->academicSession->label(),
            'semester' => $semester->semester->name,
            'publishedAt' => $semester->results_published_at?->toIso8601String(),
        ];
    }
}
