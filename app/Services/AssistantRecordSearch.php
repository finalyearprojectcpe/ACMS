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

class AssistantRecordSearch
{
    public function __construct(
        private readonly CurrencyFormatter $currency,
        private readonly ResultService $results,
    ) {}

    /** @param array<string, mixed> $input */
    public function search(AdvisorProfile $advisor, array $input): array
    {
        $term = trim(mb_substr((string) ($input['term'] ?? ''), 0, 80));
        $set = trim(mb_substr((string) ($input['set'] ?? ''), 0, 100));
        $setIds = $advisor->studentSets()->get()
            ->filter(fn (StudentSet $item): bool => $set === ''
                || mb_strtolower($item->label()) === mb_strtolower($set)
                || mb_strtolower($item->filterKey()) === mb_strtolower($set)
                || $item->routeParameters()['entrySession'] === $set)
            ->pluck('id')->all();
        $session = preg_replace('/\D/', '', (string) ($input['session'] ?? ''));
        $filters = [
            'term' => $term,
            'like' => '%'.$term.'%',
            'setIds' => $setIds,
            'status' => (string) ($input['status'] ?? 'any'),
            'session' => $session === '' ? null : (strlen($session) === 8 ? $session : 'invalid'),
            'semester' => (string) ($input['semester'] ?? ''),
            'grade' => (string) ($input['grade'] ?? ''),
            'page' => min(100, max(1, (int) ($input['page'] ?? 1))),
        ];

        return match ((string) ($input['category'] ?? '')) {
            'sets' => $this->sets($advisor, $filters),
            'students' => $this->students($advisor, $filters),
            'join_requests' => $this->joinRequests($advisor, $filters),
            'registrations' => $this->registrations($advisor, $filters),
            'catalog' => $this->catalog($advisor, $filters),
            'courses' => $this->courses($advisor, $filters),
            'results' => $this->courseResults($advisor, $filters),
            'performance' => $this->performance($advisor, $filters),
            'dues' => $this->dues($advisor, $filters),
            'due_assignments' => $this->dueAssignments($advisor, $filters),
            'payments' => $this->payments($advisor, $filters),
            default => $this->result(0, [], $filters['page']),
        };
    }

    private function sets(AdvisorProfile $advisor, array $filters): array
    {
        $sets = $advisor->studentSets()->with(['currentAcademicYear', 'currentSemester'])->withCount('students')
            ->whereIn('id', $filters['setIds'])->orderByDesc('entry_start_year')->get()
            ->filter(fn (StudentSet $set): bool => $filters['term'] === '' || str_contains(mb_strtolower($set->label()), mb_strtolower($filters['term'])));

        return $this->result($sets->count(), $sets->forPage($filters['page'], 20)->map(fn (StudentSet $set): array => [
            'title' => $set->label(),
            'detail' => $set->students_count.' students · '.($set->currentAcademicYear?->name ?? 'No current level').' · '.($set->currentSemester?->name ?? 'No current semester'),
            'url' => route('student-sets.show', $set->routeParameters()),
        ])->values()->all(), $filters['page']);
    }

    private function students(AdvisorProfile $advisor, array $filters): array
    {
        $query = StudentProfile::query()->where('advisor_profile_id', $advisor->id)
            ->whereIn('student_set_id', $filters['setIds'])
            ->where(fn ($query) => $query->where('registration_number', 'like', $filters['like'])
                ->orWhere('official_name', 'like', $filters['like']));

        return $this->result($query->count(), $query->with(['user', 'studentSet'])->orderBy('registration_number')->forPage($filters['page'], 20)->get()
            ->map(fn (StudentProfile $student): array => [
                'title' => $student->official_name,
                'detail' => $student->registration_number.' · '.$student->studentSet->label(),
                'url' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
            ])->all(), $filters['page']);
    }

    private function joinRequests(AdvisorProfile $advisor, array $filters): array
    {
        $query = StudentJoinRequest::query()
            ->whereHas('studentProfile', fn ($students) => $students
                ->where('advisor_profile_id', $advisor->id)
                ->whereIn('student_set_id', $filters['setIds']))
            ->whereHas('user', fn ($users) => $users->whereNotNull('email_verified_at'))
            ->when(in_array($filters['status'], ['pending', 'approved', 'rejected'], true),
                fn ($requests) => $requests->where('status', $filters['status']))
            ->where(fn ($requests) => $requests
                ->whereHas('studentProfile', fn ($students) => $students
                    ->where('official_name', 'like', $filters['like'])
                    ->orWhere('registration_number', 'like', $filters['like']))
                ->orWhereHas('user', fn ($users) => $users->where('email', 'like', $filters['like'])));

        return $this->result($query->count(), $query->with(['studentProfile.studentSet', 'user'])
            ->orderByDesc('id')->forPage($filters['page'], 20)->get()
            ->map(fn (StudentJoinRequest $request): array => [
                'title' => $request->studentProfile->official_name,
                'detail' => $request->studentProfile->registration_number.' · '.$request->user->email.' · '.$request->status,
                'url' => route('student-sets.show', $request->studentProfile->studentSet->routeParameters()),
            ])->all(), $filters['page']);
    }

    private function catalog(AdvisorProfile $advisor, array $filters): array
    {
        $query = Course::query()->whereIn('student_set_id', $filters['setIds'])
            ->whereHas('studentSet', fn ($sets) => $sets->where('advisor_profile_id', $advisor->id))
            ->where(fn ($courses) => $courses->where('code', 'like', $filters['like'])
                ->orWhere('title', 'like', $filters['like']));

        return $this->result($query->count(), $query->with('studentSet')->orderBy('code')
            ->forPage($filters['page'], 20)->get()->map(fn (Course $course): array => [
                'title' => $course->code.' '.$course->title,
                'detail' => $course->studentSet->label().' · '.$course->credit_units.' units'.($course->is_elective ? ' · Elective' : ''),
                'url' => route('student-sets.catalog.show', $course->studentSet->routeParameters()),
            ])->all(), $filters['page']);
    }

    private function registrations(AdvisorProfile $advisor, array $filters): array
    {
        $query = CourseRegistration::query()
            ->when(in_array($filters['status'], [CourseRegistration::StatusDraft, CourseRegistration::StatusAssigned], true),
                fn ($query) => $query->where('status', $filters['status']))
            ->whereHas('studentProfile', fn ($students) => $students->where('advisor_profile_id', $advisor->id)
                ->whereIn('student_set_id', $filters['setIds']))
            ->where(fn ($query) => $query->whereHas('studentProfile', fn ($students) => $students
                ->where('registration_number', 'like', $filters['like'])
                ->orWhere('official_name', 'like', $filters['like']))
                ->orWhereHas('items', fn ($items) => $items->where('course_code', 'like', $filters['like'])))
            ->whereHas('academicSessionSemester', fn ($period) => $this->filterPeriod($period, $filters));

        return $this->result($query->count(), $query->with(['studentProfile.user', 'studentProfile.studentSet', 'academicSessionSemester.academicSession', 'academicSessionSemester.semester'])
            ->withSum('items', 'credit_units')
            ->orderByDesc('id')->forPage($filters['page'], 20)->get()->map(fn (CourseRegistration $item): array => [
                'title' => $item->studentProfile->official_name.' · '.$item->academicSessionSemester->semester->name,
                'detail' => $item->studentProfile->studentSet->label().' · '.$item->academicSessionSemester->academicSession->label().' Session · '.$item->status.' · '.($item->items_sum_credit_units ?? 0).' units',
                'url' => route('student-sets.assignments.show', [
                    ...$item->studentProfile->studentSet->routeParameters(),
                    'sessionYears' => $item->academicSessionSemester->academicSession->routeYears(),
                    'semester' => $item->academicSessionSemester->semester->slug,
                    'registrationNumber' => $item->studentProfile->routeRegistrationNumber(),
                ]),
            ])->all(), $filters['page']);
    }

    private function courses(AdvisorProfile $advisor, array $filters): array
    {
        $query = CourseOffering::query()
            ->whereHas('course.studentSet', fn ($sets) => $sets->where('advisor_profile_id', $advisor->id)
                ->whereIn('id', $filters['setIds']))
            ->where(fn ($offerings) => $offerings->where('course_title', 'like', $filters['like'])
                ->orWhereHas('course', fn ($courses) => $courses->where('code', 'like', $filters['like'])))
            ->when(in_array($filters['status'], ['published', 'unpublished'], true),
                fn ($query) => $filters['status'] === 'published'
                    ? $query->whereNotNull('results_published_at')
                    : $query->whereNull('results_published_at'))
            ->whereHas('academicSessionSemester', fn ($period) => $this->filterPeriod($period, $filters));

        return $this->result($query->count(), $query->with(['course', 'academicSessionSemester.academicSession.studentSet', 'academicSessionSemester.semester'])
            ->latest('id')->forPage($filters['page'], 20)->get()->map(function (CourseOffering $item): array {
                $session = $item->academicSessionSemester->academicSession;

                return [
                    'title' => $item->course->code.' '.$item->course_title,
                    'detail' => $session->studentSet->label().' · '.$session->label().' Session · '.$item->academicSessionSemester->semester->name.' · '.$item->credit_units.' units'.($item->is_elective ? ' · Elective' : '').' · '.($item->results_published_at ? 'Results published' : 'Results unpublished'),
                    'url' => route('student-sets.courses.show', [
                        ...$session->studentSet->routeParameters(),
                        'session' => $session->routeYears(),
                        'semester' => $item->academicSessionSemester->semester->slug,
                    ]),
                ];
            })->all(), $filters['page']);
    }

    private function courseResults(AdvisorProfile $advisor, array $filters): array
    {
        $query = CourseRegistrationItem::query()
            ->whereHas('courseRegistration.studentProfile', fn ($students) => $students
                ->where('advisor_profile_id', $advisor->id)
                ->whereIn('student_set_id', $filters['setIds']))
            ->whereHas('courseRegistration', fn ($registrations) => $registrations
                ->where('status', CourseRegistration::StatusAssigned)
                ->whereHas('academicSessionSemester', fn ($period) => $this->filterPeriod($period, $filters)))
            ->where(fn ($items) => $items->where('course_code', 'like', $filters['like'])
                ->orWhere('course_title', 'like', $filters['like'])
                ->orWhereHas('courseRegistration.studentProfile', fn ($students) => $students
                    ->where('official_name', 'like', $filters['like'])
                    ->orWhere('registration_number', 'like', $filters['like'])))
            ->when($filters['grade'] !== '', fn ($items) => $filters['grade'] === 'missing'
                ? $items->where('missing_script', true)
                : $items->where('grade', $filters['grade']))
            ->when(in_array($filters['status'], ['published', 'unpublished'], true),
                fn ($items) => $items->whereHas('courseOffering', fn ($offerings) => $filters['status'] === 'published'
                    ? $offerings->whereNotNull('results_published_at')
                    : $offerings->whereNull('results_published_at')));

        return $this->result($query->count(), $query->with([
            'courseOffering.course', 'courseRegistration.studentProfile.studentSet',
            'courseRegistration.academicSessionSemester.academicSession',
            'courseRegistration.academicSessionSemester.semester',
        ])->orderByDesc('id')->forPage($filters['page'], 20)->get()
            ->map(function (CourseRegistrationItem $item): array {
                $student = $item->courseRegistration->studentProfile;
                $period = $item->courseRegistration->academicSessionSemester;

                return [
                    'title' => $student->official_name.' · '.$item->course_code,
                    'detail' => $student->registration_number.' · '.$period->academicSession->label().' Session · '.$period->semester->name.' · '.($item->missing_script ? 'Missing script' : ($item->grade ? 'Grade '.$item->grade : 'Awaiting grade')).' · '.($item->courseOffering?->results_published_at ? 'Published' : 'Unpublished'),
                    'url' => route('student-sets.results.course', [
                        ...$student->studentSet->routeParameters(),
                        'sessionYears' => $period->academicSession->routeYears(),
                        'semester' => $period->semester->slug,
                        'courseCode' => $item->courseOffering->course->urlCode(),
                    ]),
                ];
            })->all(), $filters['page']);
    }

    private function performance(AdvisorProfile $advisor, array $filters): array
    {
        $query = StudentProfile::query()->where('advisor_profile_id', $advisor->id)
            ->whereIn('student_set_id', $filters['setIds'])
            ->where(fn ($students) => $students->where('official_name', 'like', $filters['like'])
                ->orWhere('registration_number', 'like', $filters['like']));
        $count = $query->count();
        $students = $query->with('studentSet')->orderBy('official_name')->forPage($filters['page'], 20)->get();
        $summaries = $this->results->summaryRowsForStudents($students->pluck('id'));

        return $this->result($count, $students->map(function (StudentProfile $student) use ($summaries, $filters): array {
            $latest = $summaries->get($student->id)
                ?->filter(fn (array $period): bool => ($filters['session'] === null
                    || $period['startYear'] === (int) substr($filters['session'], 0, 4))
                    && ($filters['semester'] === ''
                        || $period['semesterOrder'] === ($filters['semester'] === 'first' ? 1 : 2)))
                ->last();
            $cgpa = $latest['cgpa'] ?? null;
            $periodLabel = $latest === null ? 'No results' : $latest['startYear'].'/'.($latest['startYear'] + 1)
                .' Session · '.($latest['semesterOrder'] === 1 ? '1st' : '2nd').' Semester';

            return [
                'title' => $student->official_name,
                'detail' => $student->registration_number.' · '.$student->studentSet->label().' · '.$periodLabel.' · GPA '.($latest['gpa'] ?? 'Not available').' · CGPA '.($cgpa ?? 'Not available').' · '.($this->results->standing($cgpa) ?? 'No standing yet'),
                'url' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
            ];
        })->all(), $filters['page']);
    }

    private function dues(AdvisorProfile $advisor, array $filters): array
    {
        $query = Due::query()->where('advisor_profile_id', $advisor->id)
            ->whereIn('student_set_id', $filters['setIds'])
            ->where('title', 'like', $filters['like'])
            ->when($filters['status'] !== 'any', fn ($query) => in_array($filters['status'], ['published', 'draft'], true)
                ? $query->where('status', $filters['status']) : $query);
        $this->filterDuePeriod($query, $filters);

        return $this->result($query->count(), $query->with('studentSet')->latest('id')->forPage($filters['page'], 20)->get()
            ->map(fn (Due $due): array => [
                'title' => $due->title,
                'detail' => $due->studentSet->label().' · '.$this->currency->nairaFromKobo((int) $due->amount_kobo).' · '.$due->status,
                'url' => route('dashboard.dues.show', ['due' => $due]),
            ])->all(), $filters['page']);
    }

    private function dueAssignments(AdvisorProfile $advisor, array $filters): array
    {
        $query = StudentDueAssignment::query()
            ->whereHas('due', fn ($dues) => $this->filterDuePeriod($dues
                ->where('advisor_profile_id', $advisor->id)
                ->whereIn('student_set_id', $filters['setIds']), $filters))
            ->when(in_array($filters['status'], ['paid', 'unpaid', 'waived'], true),
                fn ($assignments) => $assignments->where('status', $filters['status'] === 'unpaid' ? StudentDueAssignment::StatusPending : $filters['status']))
            ->where(fn ($assignments) => $assignments
                ->whereHas('studentProfile', fn ($students) => $students
                    ->where('official_name', 'like', $filters['like'])
                    ->orWhere('registration_number', 'like', $filters['like']))
                ->orWhereHas('due', fn ($dues) => $dues->where('title', 'like', $filters['like'])));

        return $this->result($query->count(), $query->with(['studentProfile', 'due'])
            ->orderByDesc('id')->forPage($filters['page'], 20)->get()
            ->map(fn (StudentDueAssignment $assignment): array => [
                'title' => $assignment->studentProfile->official_name.' · '.$assignment->due->title,
                'detail' => $assignment->studentProfile->registration_number.' · '.$this->currency->nairaFromKobo((int) $assignment->amount_kobo).' · '.($assignment->status === StudentDueAssignment::StatusPending ? 'Unpaid' : $assignment->status).($assignment->waiver_reason ? ' · '.$assignment->waiver_reason : ''),
                'url' => route('dashboard.dues.show', ['due' => $assignment->due]),
            ])->all(), $filters['page']);
    }

    private function payments(AdvisorProfile $advisor, array $filters): array
    {
        $query = DuePayment::query()->where('advisor_profile_id', $advisor->id)
            ->whereIn('status', in_array($filters['status'], ['initialized', 'success', 'failed', 'abandoned', 'reversed'], true)
                ? [$filters['status']] : [DuePayment::StatusSuccess])
            ->whereHas('studentProfile', fn ($students) => $students->whereIn('student_set_id', $filters['setIds']))
            ->where(fn ($payments) => $payments->whereHas('studentProfile', fn ($students) => $students
                ->where('official_name', 'like', $filters['like'])
                ->orWhere('registration_number', 'like', $filters['like']))
                ->orWhereHas('assignment.due', fn ($dues) => $dues->where('title', 'like', $filters['like'])))
            ->whereHas('assignment.due', fn ($dues) => $this->filterDuePeriod($dues, $filters));

        return $this->result($query->count(), $query->with(['studentProfile.user', 'assignment.due'])
            ->orderByDesc('id')->forPage($filters['page'], 20)->get()->map(fn (DuePayment $payment): array => [
                'title' => $payment->studentProfile->official_name.' · '.$payment->assignment->due->title,
                'detail' => $this->currency->nairaFromKobo((int) $payment->amount_kobo).' · '.$payment->status.' · '.$payment->paid_at?->toDateString(),
                'url' => route('dashboard.dues.show', ['due' => $payment->assignment->due]),
            ])->all(), $filters['page']);
    }

    private function filterPeriod(Builder $period, array $filters): Builder
    {
        return $period
            ->when($filters['session'] !== null, fn ($query) => $query->whereHas('academicSession',
                fn ($session) => $this->filterSession($session, $filters['session'])))
            ->when($filters['semester'] !== '', fn ($query) => $query->whereHas('semester',
                fn ($semester) => $semester->where('slug', $filters['semester'])));
    }

    private function filterSession(Builder $session, string $years): Builder
    {
        return $session->where('start_year', (int) substr($years, 0, 4))
            ->where('end_year', (int) substr($years, 4, 4));
    }

    private function filterDuePeriod(Builder $dues, array $filters): Builder
    {
        return $dues
            ->when($filters['session'] !== null, fn ($query) => $query->whereHas('academicSession',
                fn ($session) => $this->filterSession($session, $filters['session'])))
            ->when($filters['semester'] !== '', fn ($query) => $query->where(fn ($period) => $period
                ->whereNull('academic_session_semester_id')
                ->orWhereHas('academicSessionSemester.semester',
                    fn ($semester) => $semester->where('slug', $filters['semester']))));
    }

    private function result(int $count, array $records, int $page): array
    {
        return ['count' => $count, 'records' => $records, 'page' => $page, 'moreAvailable' => $count > $page * 20];
    }
}
