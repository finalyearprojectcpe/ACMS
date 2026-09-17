<?php

namespace App\Services;

use App\CourseCategory;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Due;
use App\Models\Semester;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseDashboardData
{
    public function __construct(
        private readonly AcademicContextService $academicContext,
        private readonly CourseRegistrationPlanner $registrationPlanner,
        private readonly DashboardPresenter $presenter,
    ) {}

    public function offerings(Request $request, Collection $sets, Collection $levels): array
    {
        $selectedSet = $sets->firstWhere('id', $request->integer('set')) ?? $sets->first();

        if (! $selectedSet instanceof StudentSet) {
            return [
                'level' => null,
                'levels' => $levels->map(fn (AcademicYear $level): array => $this->presenter->level($level))->all(),
                'academicSession' => null,
                'selectedSet' => null,
                'assignedSets' => [],
                'semesters' => [],
            ];
        }

        $level = $levels->firstWhere('slug', $request->string('level')->toString())
            ?? $this->academicContext->academicYearForSet($selectedSet)
            ?? $levels->first();
        $session = $level instanceof AcademicYear
            ? $this->academicContext->sessionForSetYear($selectedSet, $level)
            : null;

        return [
            'level' => $level instanceof AcademicYear ? $this->presenter->level($level) : null,
            'levels' => $levels->map(fn (AcademicYear $item): array => $this->presenter->level($item))->all(),
            'academicSession' => $session instanceof AcademicSession
                ? $this->presenter->academicSession($session)
                : null,
            'selectedSet' => $this->presenter->studentSet($selectedSet),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->presenter->studentSet($set))->values()->all(),
            'semesters' => $level instanceof AcademicYear && $session instanceof AcademicSession
                ? Semester::query()->orderBy('sort_order')->get()->map(
                    fn (Semester $semester): array => $this->semesterOfferings(
                        $selectedSet,
                        $session,
                        $level,
                        $semester,
                    ),
                )->all()
                : [],
        ];
    }

    public function offeringForm(
        StudentSet $set,
        AcademicSession $session,
        AcademicYear $level,
        Semester $semester,
        ?CourseOffering $offering = null,
    ): array {
        $target = $this->yearSemester($level, $semester);
        $previous = CourseOffering::query()
            ->with(['course', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('student_set_id', $set->id)
            ->whereNull('archived_at')
            ->where(function ($query) use ($session, $target): void {
                $query->where('academic_session_id', '<', $session->id)
                    ->orWhere(fn ($query) => $query
                        ->where('academic_session_id', $session->id)
                        ->where('academic_year_semester_id', '<', $target->id));
            })
            ->get()
            ->unique('course_id')
            ->sortBy(fn (CourseOffering $item): string => sprintf(
                '%02d-%02d-%s',
                $item->academicYearSemester->academicYear->year_number,
                $item->academicYearSemester->semester->sort_order,
                $item->course->code,
            ))
            ->values();
        $offering?->loadMissing(['course', 'prerequisiteCourses']);

        return [
            'mode' => $offering instanceof CourseOffering ? 'edit' : 'create',
            'academicSession' => $this->presenter->academicSession($session),
            'level' => $this->presenter->level($level),
            'semester' => [
                'id' => (int) $semester->id,
                'name' => $semester->name,
                'slug' => $semester->slug,
            ],
            'offering' => $offering instanceof CourseOffering ? [
                'id' => (int) $offering->id,
                'code' => $offering->course->code,
                'title' => $offering->course_title,
                'creditUnits' => (int) $offering->credit_units,
                'category' => $offering->category->value,
                'prerequisiteCourseIds' => $offering->prerequisiteCourses
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            ] : null,
            'studentSetId' => (int) $set->id,
            'prerequisiteOptions' => $previous->map(fn (CourseOffering $item): array => [
                'id' => (int) $item->course_id,
                'code' => $item->course->code,
                'title' => $item->course_title,
                'units' => (int) $item->credit_units,
                'level' => $item->academicYearSemester->academicYear->name,
                'semester' => $item->academicYearSemester->semester->name,
            ])->all(),
        ];
    }

    public function studentHome(StudentProfile $student): array
    {
        $current = $this->currentRegistrationContext($student, required: false);
        $registration = $current === null ? null : CourseRegistration::query()
            ->with('items')
            ->where('student_profile_id', $student->id)
            ->where('academic_session_id', $current[0]->id)
            ->where('academic_year_semester_id', $current[1]->id)
            ->first();
        $courses = $registration?->items ?? collect();
        $pendingDues = $student->dueAssignments()
            ->with('due')
            ->where('status', StudentDueAssignment::StatusPending)
            ->whereHas('due', fn ($query) => $query->where('status', Due::StatusPublished))
            ->get();

        return [
            'currentSemester' => $current === null ? null : [
                'name' => $current[1]->semester->name,
                'level' => $current[1]->academicYear->name,
                'session' => $current[0]->name,
            ],
            'courseAdvisor' => $student->advisorProfile->user->name,
            'totalCourseUnits' => (int) $courses->sum('credit_units'),
            'courses' => $courses->map(fn (CourseRegistrationItem $item): array => [
                'code' => $item->course_code,
                'title' => $item->course_title,
                'units' => (int) $item->credit_units,
                'type' => $this->categoryLabel($item->category),
            ])->all(),
            'pendingDues' => $pendingDues->map(fn (StudentDueAssignment $assignment): array => [
                'title' => $assignment->due->title,
                'context' => $student->studentSet->name,
                'amount' => $this->presenter->amount((int) $assignment->amount_kobo),
                'dueDate' => $assignment->due->due_on?->toFormattedDateString() ?? 'No due date',
            ])->all(),
        ];
    }

    public function studentRegistration(StudentProfile $student): array
    {
        [$session, $slot] = $this->currentRegistrationContext($student, required: true);
        $plan = $this->registrationPlanner->plan($student, $session, $slot);
        $registration = CourseRegistration::query()
            ->with('items')
            ->where('student_profile_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('academic_year_semester_id', $slot->id)
            ->first();
        $history = $student->courseRegistrations()
            ->with([
                'items',
                'academicSession',
                'academicYearSemester.academicYear',
                'academicYearSemester.semester',
            ])
            ->when(
                $registration instanceof CourseRegistration,
                fn ($query) => $query->whereKeyNot($registration->id),
            )
            ->latest('submitted_at')
            ->get();

        return [
            'currentSemester' => [
                'name' => $slot->semester->name,
                'level' => $slot->academicYear->name,
                'session' => $session->name,
            ],
            'maximumCourseUnits' => (int) $student->studentSet->maximum_course_units,
            'advisorName' => $student->advisorProfile->user->name,
            'reviewStatus' => $registration === null ? 'Not submitted' : $this->statusLabel($registration->status),
            'feedbackNote' => $registration?->feedback_note,
            'registrationId' => $registration?->id,
            'selectedUnits' => (int) ($registration?->items->sum('credit_units') ?? 0),
            'selectedCourses' => $registration?->items
                ->map(fn (CourseRegistrationItem $item): array => $this->registrationItem($item))
                ->all() ?? [],
            'availableCourses' => $plan['availableCourses'],
            'selectableCourses' => $plan['selectableCourses'],
            'courseGroups' => $plan['courseGroups'],
            'previousRegistrations' => $history
                ->map(fn (CourseRegistration $item): array => $this->registrationSummary($item, false))
                ->all(),
        ];
    }

    public function adviserRegistrations(
        AdvisorProfile $advisor,
        ?CourseRegistration $review = null,
    ): array {
        $sets = $advisor->studentSets()
            ->with('entryAcademicSession')
            ->orderByDesc('entry_academic_session_id')
            ->get();
        $registrations = CourseRegistration::query()
            ->with([
                'items',
                'studentProfile.user',
                'studentSet.entryAcademicSession',
                'academicSession',
                'academicYearSemester.academicYear',
                'academicYearSemester.semester',
            ])
            ->where('advisor_profile_id', $advisor->id)
            ->where('status', CourseRegistration::StatusSubmitted)
            ->latest('submitted_at')
            ->get();

        return [
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->presenter->studentSet($set))->all(),
            'registrations' => $registrations
                ->map(fn (CourseRegistration $item): array => $this->registrationSummary($item, true))
                ->all(),
            'review' => $review instanceof CourseRegistration ? $this->registrationReview($review) : null,
        ];
    }

    private function semesterOfferings(
        StudentSet $set,
        AcademicSession $session,
        AcademicYear $level,
        Semester $semester,
    ): array {
        $slot = $this->yearSemester($level, $semester);
        $offerings = CourseOffering::query()
            ->with(['course', 'prerequisiteCourses'])
            ->where('student_set_id', $set->id)
            ->where('academic_session_id', $session->id)
            ->where('academic_year_semester_id', $slot->id)
            ->whereNull('archived_at')
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();

        return [
            'id' => $semester->id,
            'name' => $semester->name,
            'slug' => $semester->slug,
            'totalUnits' => (int) $offerings->sum('credit_units'),
            'normalCourseCount' => $offerings->where('category', CourseCategory::Normal)->count(),
            'electiveCourseCount' => $offerings->where('category', CourseCategory::Elective)->count(),
            'offerings' => $offerings->map(fn (CourseOffering $offering): array => [
                'id' => (int) $offering->id,
                'courseId' => (int) $offering->course_id,
                'code' => $offering->course->code,
                'title' => $offering->course_title,
                'units' => (int) $offering->credit_units,
                'category' => $offering->category->value,
                'prerequisites' => $offering->prerequisiteCourses->map(fn (Course $course): array => [
                    'id' => (int) $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                ])->all(),
                'canEdit' => true,
            ])->all(),
        ];
    }

    private function registrationSummary(CourseRegistration $registration, bool $includeStudent): array
    {
        $registration->loadMissing([
            'items',
            'studentProfile.user',
            'academicSession',
            'academicYearSemester.academicYear',
            'academicYearSemester.semester',
        ]);
        $payload = [
            'id' => (int) $registration->id,
            'status' => $this->statusLabel($registration->status),
            'semester' => [
                'level' => $registration->academicYearSemester->academicYear->name,
                'name' => $registration->academicYearSemester->semester->name,
                'session' => $registration->academicSession->name,
            ],
            'totalUnits' => (int) $registration->items->sum('credit_units'),
            'courses' => $registration->items
                ->map(fn (CourseRegistrationItem $item): array => $this->registrationItem($item))
                ->all(),
            'reviewUrl' => route('dashboard.course-registration.show', [
                'courseRegistration' => $registration,
            ]),
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
        $registration->loadMissing([
            'advisorProfile',
            'studentProfile.user',
            'studentSet.entryAcademicSession',
            'items',
        ]);

        return [
            ...$this->registrationSummary($registration, true),
            'set' => $this->presenter->studentSet($registration->studentSet),
            'programme' => $this->presenter->programme($registration->advisorProfile),
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
            'category' => $this->categoryLabel($item->category),
            'categoryValue' => CourseCategory::tryFrom($item->category)?->value
                ?? CourseCategory::Normal->value,
            'isElective' => CourseCategory::tryFrom($item->category) === CourseCategory::Elective,
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

    private function categoryLabel(string $category): string
    {
        return CourseCategory::tryFrom($category)?->label() ?? Str::headline($category);
    }

    /** @return array{0: AcademicSession, 1: AcademicYearSemester}|null */
    private function currentRegistrationContext(StudentProfile $student, bool $required): ?array
    {
        $session = $this->academicContext->currentSession($student->advisorProfile)
            ?? $student->advisorProfile->academicSessions()->latest('starts_on')->first();
        $year = $session instanceof AcademicSession
            ? $this->academicContext->academicYearForStudent($student, $session)
            : null;
        $semester = $session instanceof AcademicSession
            ? $this->academicContext->currentSemesterInstance($session)?->semester
            : null;
        $semester ??= Semester::query()->orderBy('sort_order')->first();

        if (! ($session instanceof AcademicSession)
            || ! ($year instanceof AcademicYear)
            || ! ($semester instanceof Semester)) {
            if ($required) {
                throw ValidationException::withMessages([
                    'registration' => 'Course registration is not available until the current academic session and semester are configured.',
                ]);
            }

            return null;
        }

        return [
            $session,
            $this->yearSemester($year, $semester)->loadMissing(['academicYear', 'semester']),
        ];
    }

    private function yearSemester(
        AcademicYear $year,
        Semester $semester,
    ): AcademicYearSemester {
        return AcademicYearSemester::query()
            ->whereBelongsTo($year)
            ->whereBelongsTo($semester)
            ->firstOrFail();
    }
}
