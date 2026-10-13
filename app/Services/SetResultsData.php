<?php

namespace App\Services;

use App\Grade;
use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\CourseOffering;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Database\Eloquent\Collection;

class SetResultsData
{
    public function __construct(private readonly DashboardPresenter $presenter, private readonly ResultService $results) {}

    public function matrix(StudentSet $set, ?string $sessionYears, ?string $semester, bool $all, bool $sessionOnly = false): array
    {
        $sessions = $set->academicSessions()->with(['academicYear', 'semesterInstances.semester'])->orderBy('start_year')->get();
        $current = $sessions->firstWhere('academic_year_id', $set->current_academic_year_id) ?? $sessions->last();
        $selectedSession = $sessionYears === null ? $current : $sessions->first(fn (AcademicSession $session): bool => $session->routeYears() === $sessionYears);
        abort_if($sessionYears !== null && $selectedSession === null, 404);
        $selectedPeriod = $selectedSession?->semesterInstances->first(
            fn (AcademicSessionSemester $period): bool => $period->semester->slug === ($semester ?? $set->currentSemester?->slug),
        ) ?? $selectedSession?->semesterInstances->sortBy('semester.sort_order')->first();
        abort_if(! $all && $semester !== null && $selectedPeriod === null, 404);

        $historySessions = $sessions->filter(fn (AcademicSession $session): bool => $current === null || $session->start_year <= $current->start_year);
        abort_if($all && $selectedSession !== null && ! $historySessions->contains('id', $selectedSession->id), 404);
        $periods = $all
            ? ($sessionOnly
                ? ($selectedSession?->semesterInstances ?? collect())
                : $historySessions->flatMap(fn (AcademicSession $session) => $session->semesterInstances))
            : collect($selectedPeriod ? [$selectedPeriod] : []);
        $offerings = CourseOffering::query()->with(['course', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->whereIn('academic_session_semester_id', $periods->pluck('id'))
            ->get()->sortBy(fn (CourseOffering $offering): string => sprintf(
                '%04d-%02d-%s',
                $offering->academicSessionSemester->academicSession->start_year,
                $offering->academicSessionSemester->semester->sort_order,
                $offering->course->code,
            ))->values();
        $students = $set->students()
            ->select(['id', 'user_id', 'official_name', 'registration_number'])
            ->with('user')
            ->orderBy('official_name')
            ->get();
        $registrations = $this->results->summaryRowsForStudents($students->pluck('id'));
        $carryoverOnly = $registrations
            ->flatMap(fn ($summaries) => $summaries->flatMap(fn (array $summary) => $summary['items']))
            ->groupBy('course_offering_id')
            ->map(fn ($items): bool => $items->every(fn (object $item): bool => $item->assignment_origin === 'carryover'));
        $columns = $offerings->map(fn (CourseOffering $offering): array => $this->column(
            $set,
            $offering,
            $carryoverOnly->get($offering->id, false),
        ))->all();
        $columnIds = array_fill_keys($offerings->pluck('id')->all(), true);

        return [
            'set' => $this->presenter->studentSet($set),
            'allHistory' => $all,
            'selectedSemesterId' => $all ? null : $selectedPeriod?->id,
            'selectedSessionPublicId' => $selectedSession?->routeYears(),
            'periods' => $this->periodNavigation($all ? $historySessions : $sessions),
            'allHistoryUrl' => route('student-sets.matrix.show', [...$set->routeParameters(), 'all' => 1]),
            'exportAvailable' => $all && CourseOffering::query()->whereIn(
                'academic_session_semester_id',
                $historySessions->flatMap(fn (AcademicSession $session) => $session->semesterInstances)->pluck('id'),
            )->exists(),
            'columns' => $columns,
            'students' => $students->map(function (StudentProfile $student) use ($registrations, $columnIds, $selectedPeriod, $periods, $all): array {
                $summaries = $registrations->get($student->id, collect());
                $visibleSummaries = $summaries->filter(fn (array $summary): bool => $periods->contains('id', $summary['periodId']));
                $periodSummary = $all ? $visibleSummaries->last() : $summaries->first(
                    fn (array $summary): bool => $summary['periodId'] === $selectedPeriod?->id,
                );
                $historyThroughPeriod = $all ? $visibleSummaries : $summaries->filter(function (array $summary) use ($selectedPeriod): bool {
                    if ($selectedPeriod === null) {
                        return false;
                    }

                    return $summary['startYear'] < $selectedPeriod->academicSession->start_year
                        || ($summary['startYear'] === $selectedPeriod->academicSession->start_year
                            && $summary['semesterOrder'] <= $selectedPeriod->semester->sort_order);
                });
                $latestComplete = $historyThroughPeriod
                    ->filter(fn (array $summary): bool => $summary['counted'])->last();
                $items = $summaries->flatMap(fn (array $summary) => $summary['items'])
                    ->filter(fn (object $item): bool => isset($columnIds[$item->course_offering_id]));
                $graded = $items->filter(fn (object $item): bool => $item->results_published_at !== null
                    && $item->grade !== null);
                $failedCourses = $graded->filter(fn (object $item): bool => $item->grade === Grade::F->value)->count();
                $gradePoints = (int) $graded->sum(fn (object $item): int => Grade::from($item->grade)->points() * (int) $item->credit_units);
                $gradedUnits = (int) $graded->sum('credit_units');
                $sessionComplete = $visibleSummaries->isNotEmpty()
                    && $visibleSummaries->every(fn (array $summary): bool => $summary['counted']);
                $cgpa = $periodSummary['cgpa'] ?? $latestComplete['cgpa'] ?? null;

                return [
                    'name' => $student->official_name,
                    'avatar' => $student->user?->profilePhotoUrl(),
                    'registrationNumber' => $student->registration_number,
                    'profileUrl' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
                    'cells' => $items->mapWithKeys(fn (object $item): array => [
                        (string) $item->course_offering_id => [
                            'grade' => $item->results_published_at !== null ? $item->grade : null,
                            'assignmentOrigin' => $item->assignment_origin,
                            'state' => $item->results_published_at !== null
                                ? ($item->missing_script ? 'missing' : 'published')
                                : ($item->grade !== null || $item->missing_script ? 'unpublished' : 'awaiting'),
                        ],
                    ])->all(),
                    'totalGradePoints' => $gradePoints,
                    'totalNumberOfUnits' => $gradedUnits,
                    'gpa' => $all ? ($sessionComplete && $gradedUnits > 0 ? round($gradePoints / $gradedUnits, 2) : null) : ($periodSummary['gpa'] ?? null),
                    'cgpa' => $cgpa,
                    'remarks' => $failedCourses > 0 ? $failedCourses.'F' : ($items->isNotEmpty() && $graded->count() === $items->count() ? 'Pass' : null),
                    'standing' => $this->results->standing($cgpa),
                ];
            })->all(),
        ];
    }

    /** @param Collection<int, AcademicSession> $sessions */
    private function periodNavigation(Collection $sessions): array
    {
        return $sessions->map(fn (AcademicSession $session): array => [
            'id' => (int) $session->id,
            'publicId' => $session->routeYears(),
            'name' => $session->label(),
            'level' => $this->presenter->level($session->academicYear),
            'semesters' => $session->semesterInstances->sortBy('semester.sort_order')->map(fn (AcademicSessionSemester $period): array => [
                'id' => (int) $period->id,
                'publicId' => $period->semester->slug,
                'name' => $period->semester->name,
                'slug' => $period->semester->slug,
            ])->values()->all(),
        ])->all();
    }

    private function column(StudentSet $set, CourseOffering $offering, bool $carryoverOnly): array
    {
        $period = $offering->academicSessionSemester;

        return [
            'id' => (string) $offering->id,
            'periodId' => (int) $period->id,
            'code' => $offering->course->code,
            'title' => $offering->course_title,
            'units' => (int) $offering->credit_units,
            'isElective' => (bool) $offering->is_elective,
            'carryoverOnly' => $carryoverOnly,
            'group' => $period->academicSession->academicYear->name.' · '.$period->semester->name,
            'session' => $period->academicSession->label(),
            'url' => route('student-sets.results.course', [
                ...$set->routeParameters(),
                'sessionYears' => $period->academicSession->routeYears(),
                'semester' => $period->semester->slug,
                'courseCode' => $offering->course->urlCode(),
            ]),
        ];
    }
}
