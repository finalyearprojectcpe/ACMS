<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use App\Models\StudentJoinRequest;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;

class AssistantReportData
{
    public function __construct(
        private readonly ResultService $results,
        private readonly StudentResultAccess $resultAccess,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function rows(AdvisorProfile|StudentProfile $owner, string $dataset): Collection
    {
        if ($owner instanceof StudentProfile && in_array($dataset, [
            'courses', 'offerings', 'dues', 'join_requests',
        ], true)) {
            throw new \InvalidArgumentException('That dataset is not available for this account.');
        }

        return match ($dataset) {
            'sets' => $this->sets($owner),
            'students' => $this->students($owner),
            'courses' => $this->courses($owner),
            'offerings' => $this->offerings($owner),
            'registrations' => $this->registrations($owner),
            'results' => $this->results($owner),
            'dues' => $this->dues($owner),
            'due_assignments' => $this->dueAssignments($owner),
            'payments' => $this->payments($owner),
            'join_requests' => $this->joinRequests($owner),
            default => collect(),
        };
    }

    /** @return list<int> */
    private function setIds(AdvisorProfile|StudentProfile $owner): array
    {
        return $owner instanceof AdvisorProfile
            ? $owner->studentSets()->pluck('id')->all()
            : [$owner->student_set_id];
    }

    /** @return Builder<StudentProfile> */
    private function studentQuery(AdvisorProfile|StudentProfile $owner): Builder
    {
        return StudentProfile::query()
            ->when($owner instanceof AdvisorProfile,
                fn (Builder $query) => $query->where('advisor_profile_id', $owner->id),
                fn (Builder $query) => $query->whereKey($owner->id));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function sets(AdvisorProfile|StudentProfile $owner): Collection
    {
        return $this->bounded(StudentSet::query()->whereIn('id', $this->setIds($owner))
            ->with(['currentAcademicYear', 'currentSemester'])->withCount('students'))
            ->map(fn (StudentSet $set): array => [
                'set' => $set->label(),
                'department' => $set->department_name,
                'entry_session' => $set->entry_start_year.'/'.$set->entry_end_year,
                'current_level' => $set->currentAcademicYear?->name,
                'current_semester' => $set->currentSemester?->name,
                'student_count' => $set->students_count,
                'invites_open' => (bool) $set->student_signup_enabled,
                '_url' => $owner instanceof AdvisorProfile
                    ? route('student-sets.show', $set->routeParameters())
                    : route('dashboard'),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function students(AdvisorProfile|StudentProfile $owner): Collection
    {
        $students = $this->bounded($this->studentQuery($owner)->with('studentSet'));
        $summaries = $owner instanceof AdvisorProfile
            ? $this->results->summaryRowsForStudents($students->pluck('id')) : collect();
        $unpaid = StudentDueAssignment::query()
            ->whereIn('student_profile_id', $students->pluck('id'))
            ->where('status', StudentDueAssignment::StatusPending)
            ->whereHas('due', fn (Builder $query) => $query->where('status', Due::StatusPublished))
            ->selectRaw('student_profile_id, COUNT(*) as total')
            ->groupBy('student_profile_id')->pluck('total', 'student_profile_id');

        return $students->map(function (StudentProfile $student) use ($owner, $summaries, $unpaid): array {
            $periods = $summaries->get($student->id);
            $latest = $periods?->last();
            $latestComplete = $periods?->filter(fn (array $period): bool => $period['counted'])->last();
            $cgpa = $latest['cgpa'] ?? null;
            $row = [
                'name' => $student->official_name,
                'registration_number' => $student->registration_number,
                'set' => $student->studentSet->label(),
                'status' => $student->status?->value ?? null,
                'unpaid_dues_count' => (int) ($unpaid[$student->id] ?? 0),
                '_url' => $owner instanceof AdvisorProfile
                    ? route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()])
                    : route('dashboard.section', ['section' => 'profile-settings']),
            ];

            if ($owner instanceof AdvisorProfile) {
                $row += [
                    'cgpa' => $cgpa,
                    'latest_gpa' => $latestComplete['gpa'] ?? null,
                    'standing' => $this->results->standing($cgpa),
                ];
            }

            return $row;
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function courses(AdvisorProfile|StudentProfile $owner): Collection
    {
        return $this->bounded(Course::query()->whereIn('student_set_id', $this->setIds($owner))
            ->with('studentSet'))
            ->map(fn (Course $course): array => [
                'set' => $course->studentSet->label(),
                'code' => $course->code,
                'title' => $course->title,
                'units' => (int) $course->credit_units,
                'elective' => (bool) $course->is_elective,
                '_url' => route('student-sets.catalog.show', $course->studentSet->routeParameters()),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function offerings(AdvisorProfile|StudentProfile $owner): Collection
    {
        return $this->bounded(CourseOffering::query()
            ->whereHas('course', fn (Builder $query) => $query->whereIn('student_set_id', $this->setIds($owner)))
            ->with(['course.studentSet', 'academicSessionSemester.academicSession', 'academicSessionSemester.semester']))
            ->map(function (CourseOffering $offering): array {
                $set = $offering->course->studentSet;
                $session = $offering->academicSessionSemester->academicSession;
                $semester = $offering->academicSessionSemester->semester;

                return [
                    'set' => $set->label(), 'session' => $session->label(),
                    'semester' => $semester->slug, 'code' => $offering->course->code,
                    'title' => $offering->course_title, 'units' => (int) $offering->credit_units,
                    'elective' => (bool) $offering->is_elective,
                    'retake_only' => (bool) $offering->is_retake_only,
                    'results_published' => $offering->results_published_at !== null,
                    '_url' => route('student-sets.courses.show', [
                        ...$set->routeParameters(), 'session' => $session->routeYears(),
                        'semester' => $semester->slug,
                    ]),
                ];
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function registrations(AdvisorProfile|StudentProfile $owner): Collection
    {
        $query = CourseRegistration::query()
            ->whereHas('studentProfile', fn (Builder $query) => $this->scopeStudent($query, $owner))
            ->when($owner instanceof StudentProfile,
                fn (Builder $query) => $query->where('status', CourseRegistration::StatusAssigned))
            ->with(['studentProfile.studentSet', 'academicSessionSemester.academicSession',
                'academicSessionSemester.semester'])->withCount('items')->withSum('items', 'credit_units');

        return $this->bounded($query)->map(function (CourseRegistration $registration) use ($owner): array {
            $student = $registration->studentProfile;
            $session = $registration->academicSessionSemester->academicSession;
            $semester = $registration->academicSessionSemester->semester;

            return [
                'student' => $student->official_name,
                'registration_number' => $student->registration_number,
                'set' => $student->studentSet->label(),
                'session' => $session->label(), 'semester' => $semester->slug,
                'status' => $registration->status,
                'course_count' => $registration->items_count,
                'total_units' => (int) ($registration->items_sum_credit_units ?? 0),
                '_url' => $owner instanceof AdvisorProfile
                    ? route('student-sets.assignments.show', [
                        ...$student->studentSet->routeParameters(),
                        'sessionYears' => $session->routeYears(),
                        'semester' => $semester->slug,
                        'registrationNumber' => $student->routeRegistrationNumber(),
                    ])
                    : route('dashboard.section', ['section' => 'courses',
                        'session' => $session->routeYears(), 'semester' => $semester->slug]),
            ];
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function results(AdvisorProfile|StudentProfile $owner): Collection
    {
        $query = CourseRegistrationItem::query()
            ->whereHas('courseRegistration', fn (Builder $query) => $query
                ->where('status', CourseRegistration::StatusAssigned)
                ->whereHas('studentProfile', fn (Builder $students) => $this->scopeStudent($students, $owner)))
            ->when($owner instanceof StudentProfile, fn (Builder $query) => $query
                ->whereHas('courseRegistration', fn (Builder $registrations) => $registrations
                    ->whereIn('academic_session_semester_id', $this->resultAccess->accessibleSemesterIds($owner)))
                ->whereHas('courseOffering', fn (Builder $offerings) => $offerings->whereNotNull('results_published_at'))
                ->where(fn (Builder $items) => $items->whereNotNull('grade')->orWhere('missing_script', true)))
            ->with(['courseOffering', 'courseRegistration.studentProfile.studentSet',
                'courseRegistration.academicSessionSemester.academicSession',
                'courseRegistration.academicSessionSemester.semester']);

        return $this->bounded($query)->map(function (CourseRegistrationItem $item) use ($owner): array {
            $student = $item->courseRegistration->studentProfile;
            $session = $item->courseRegistration->academicSessionSemester->academicSession;
            $semester = $item->courseRegistration->academicSessionSemester->semester;

            return [
                'student' => $student->official_name, 'registration_number' => $student->registration_number,
                'set' => $student->studentSet->label(), 'session' => $session->label(),
                'semester' => $semester->slug, 'course_code' => $item->course_code,
                'course_title' => $item->course_title, 'grade' => $item->grade,
                'missing_script' => (bool) $item->missing_script,
                'published' => $item->courseOffering?->results_published_at !== null,
                'elective' => (bool) $item->is_elective,
                'assignment_origin' => $item->assignment_origin,
                'units' => (int) $item->credit_units,
                '_url' => $owner instanceof AdvisorProfile
                    ? route('dashboard.students.results.semester', [
                        'registrationNumber' => $student->routeRegistrationNumber(),
                        'sessionYears' => $session->routeYears(), 'semester' => $semester->slug,
                    ])
                    : route('dashboard.section', ['section' => 'results',
                        'session' => $session->routeYears(), 'semester' => $semester->slug]),
            ];
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function dues(AdvisorProfile|StudentProfile $owner): Collection
    {
        return $this->bounded(Due::query()->whereIn('student_set_id', $this->setIds($owner))
            ->with(['studentSet', 'academicSession', 'academicSessionSemester.semester']))
            ->map(fn (Due $due): array => [
                'set' => $due->studentSet->label(), 'title' => $due->title,
                'session' => $due->academicSession->label(),
                'semester' => $due->academicSessionSemester?->semester?->slug,
                'status' => $due->status, 'amount_kobo' => (int) $due->amount_kobo,
                '_url' => route('dashboard.dues.show', ['due' => $due]),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function dueAssignments(AdvisorProfile|StudentProfile $owner): Collection
    {
        $query = StudentDueAssignment::query()
            ->whereHas('studentProfile', fn (Builder $query) => $this->scopeStudent($query, $owner))
            ->whereHas('due', fn (Builder $query) => $query
                ->whereIn('student_set_id', $this->setIds($owner))
                ->when($owner instanceof StudentProfile,
                    fn (Builder $query) => $query->where('status', Due::StatusPublished)))
            ->with(['studentProfile.studentSet', 'due.academicSession',
                'due.academicSessionSemester.semester']);

        return $this->bounded($query)->map(function (StudentDueAssignment $assignment) use ($owner): array {
            $student = $assignment->studentProfile;
            $due = $assignment->due;

            return [
                'student' => $student->official_name,
                'registration_number' => $student->registration_number,
                'set' => $student->studentSet->label(),
                'due' => $due->title, 'session' => $due->academicSession->label(),
                'semester' => $due->academicSessionSemester?->semester?->slug,
                'status' => $assignment->status === StudentDueAssignment::StatusPending
                    ? 'unpaid' : $assignment->status,
                'amount_kobo' => (int) $assignment->amount_kobo,
                'paid_at' => $assignment->paid_at?->toIso8601String(),
                '_url' => $owner instanceof AdvisorProfile
                    ? route('dashboard.dues.show', ['due' => $due])
                    : route('dashboard.section', ['section' => 'student-dues']),
            ];
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function payments(AdvisorProfile|StudentProfile $owner): Collection
    {
        $query = DuePayment::query()
            ->when($owner instanceof AdvisorProfile,
                fn (Builder $query) => $query->where('advisor_profile_id', $owner->id),
                fn (Builder $query) => $query->where('student_profile_id', $owner->id))
            ->with(['studentProfile.studentSet', 'assignment.due']);

        return $this->bounded($query)->map(function (DuePayment $payment) use ($owner): array {
            $student = $payment->studentProfile;

            return [
                'student' => $student->official_name,
                'registration_number' => $student->registration_number,
                'set' => $student->studentSet->label(),
                'due' => $payment->assignment->due->title,
                'status' => $payment->status,
                'amount_kobo' => (int) $payment->amount_kobo,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'reference' => $payment->reference,
                '_url' => $owner instanceof AdvisorProfile
                    ? route('dashboard.dues.show', ['due' => $payment->assignment->due])
                    : route('dashboard.section', ['section' => 'student-dues']),
            ];
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function joinRequests(AdvisorProfile|StudentProfile $owner): Collection
    {
        return $this->bounded(StudentJoinRequest::query()
            ->whereHas('studentProfile', fn (Builder $query) => $this->scopeStudent($query, $owner))
            ->whereHas('user', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->with(['studentProfile.studentSet']))
            ->map(fn (StudentJoinRequest $request): array => [
                'student' => $request->studentProfile->official_name,
                'registration_number' => $request->studentProfile->registration_number,
                'set' => $request->studentProfile->studentSet->label(),
                'status' => $request->status,
                'submitted_at' => $request->created_at->toIso8601String(),
                '_url' => route('student-sets.show', $request->studentProfile->studentSet->routeParameters()),
            ]);
    }

    private function scopeStudent(Builder $query, AdvisorProfile|StudentProfile $owner): Builder
    {
        return $owner instanceof AdvisorProfile
            ? $query->where('advisor_profile_id', $owner->id)
            : $query->whereKey($owner->id);
    }

    private function bounded(Builder $query): Collection
    {
        $rows = $query->limit(10001)->get();

        if ($rows->count() > 10000) {
            throw new RuntimeException('This report is too broad. Narrow it to a set or semester.');
        }

        return $rows;
    }
}
