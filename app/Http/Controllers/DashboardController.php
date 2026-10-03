<?php

namespace App\Http\Controllers;

use App\AccountType;
use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AdvisorProfile;
use App\Models\AssistantConversation;
use App\Models\Course;
use App\Models\Due;
use App\Models\StudentDueAssignment;
use App\Models\StudentJoinRequest;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\AdviserActivityData;
use App\Services\AdviserJoinRequestData;
use App\Services\AssistantService;
use App\Services\CourseDashboardData;
use App\Services\DashboardAccess;
use App\Services\DashboardPresenter;
use App\Services\DueDashboardData;
use App\Services\ResultDashboardData;
use App\Services\SetResultsData;
use App\Services\StudentResultAccess;
use App\Services\StudentResultStatement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    private const array ROLE_SECTIONS = [
        'adviser' => ['home', 'student-sets', 'students', 'join-requests', 'courses', 'set-results', 'student-dues', 'settings', 'assistant'],
        'student' => ['home', 'dues', 'courses', 'results', 'profile-settings', 'assistant'],
    ];

    private const array SECTION_TITLES = [
        'home' => 'Home',
        'students' => 'Students',
        'join-requests' => 'Student Join Requests',
        'courses' => 'Courses',
        'set-results' => 'Set Results',
        'results' => 'Results',
        'dues' => 'Dues',
        'student-sets' => 'Student Sets',
        'student-dues' => 'Student Dues',
        'settings' => 'Settings',
        'profile-settings' => 'Profile',
        'assistant' => 'Ask AI',
    ];

    public function __construct(
        private readonly DashboardAccess $access,
        private readonly DashboardPresenter $presenter,
        private readonly CourseDashboardData $courses,
        private readonly DueDashboardData $dues,
        private readonly AdviserActivityData $activity,
        private readonly AdviserJoinRequestData $joins,
        private readonly AssistantService $assistant,
        private readonly ResultDashboardData $results,
        private readonly SetResultsData $setResults,
        private readonly StudentResultStatement $statements,
        private readonly StudentResultAccess $resultAccess,
    ) {}

    public function home(Request $request): InertiaResponse
    {
        return $this->renderDashboard($request, 'home');
    }

    public function section(Request $request, string $section): InertiaResponse|RedirectResponse
    {
        if ($section === 'courses' && $this->access->user($request)->isAdviser()) {
            $set = $this->studentSetsFor($this->access->advisor($request))->first();

            return $set instanceof StudentSet
                ? to_route('student-sets.courses.show', $set->routeParameters())
                : $this->renderDashboard($request, 'courses');
        }
        if ($section === 'set-results' && $this->access->user($request)->isAdviser()) {
            $set = $this->studentSetsFor($this->access->advisor($request))->first();

            return $set instanceof StudentSet
                ? to_route('student-sets.matrix.show', $set->routeParameters())
                : $this->renderDashboard($request, 'set-results');
        }

        if ($section === 'student-sets' && $this->access->user($request)->isAdviser()) {
            $studentSet = $this->studentSetsFor($this->access->advisor($request))->first();

            if ($studentSet instanceof StudentSet) {
                return to_route('student-sets.show', $studentSet->routeParameters());
            }
        }

        if ($section === 'student-dues' && $this->access->user($request)->isAdviser()) {
            $studentSet = $this->studentSetsFor($this->access->advisor($request))->first();

            if ($studentSet instanceof StudentSet) {
                return to_route('student-sets.dues.show', $studentSet->routeParameters());
            }
        }

        return $this->renderDashboard($request, $section);
    }

    public function createStudentSet(Request $request): InertiaResponse
    {
        return $this->renderDashboard($request, 'student-sets', overrides: [
            'sectionTitle' => 'Add New Set',
        ]);
    }

    public function showStudentSet(
        Request $request,
        string $departmentSlug,
        string $entrySession,
    ): InertiaResponse {
        $advisor = $this->access->advisor($request);
        $selectedSet = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        $request->merge(['set' => $selectedSet->filterKey()]);

        return $this->renderDashboard($request, 'students', overrides: [
            'sectionTitle' => $selectedSet->label(),
            'activeStudentSetId' => (int) $selectedSet->id,
        ]);
    }

    public function editStudentSet(Request $request, string $departmentSlug, string $entrySession): InertiaResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);

        return $this->renderDashboard($request, 'student-sets', overrides: [
            'sectionTitle' => 'Set Settings',
            'activeStudentSetId' => (int) $set->id,
            'studentSetSettings' => [
                'set' => $this->presenter->studentSet($set),
                'sessions' => $this->courses->settingsSessions($set),
                'logoUrl' => $set->logo_path ? route('student-sets.logo.show', $set->routeParameters()).'?v='.rawurlencode($set->updated_at?->toISOString() ?? '') : null,
            ],
        ]);
    }

    public function showStudentProfile(Request $request, string $registrationNumber): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $student = $advisor->students()
            ->with(['user', 'studentSet.currentAcademicYear'])
            ->where('registration_number', StudentProfile::registrationNumberFromRoute($registrationNumber))
            ->firstOrFail();

        return $this->renderDashboard($request, 'students', overrides: [
            'sectionTitle' => $student->official_name,
            'activeStudentSetId' => (int) $student->student_set_id,
            'studentRecord' => $this->presenter->studentRecord($student) + [
                'courseAssignments' => $this->courses->studentAssignments($student),
                'results' => $this->results->studentOverview($student),
                'dues' => $this->dues->studentRecordDues($student),
            ],
        ]);
    }

    public function rejectJoinRequest(Request $request, StudentJoinRequest $studentJoinRequest): InertiaResponse
    {
        $detail = $this->joins->detail($this->access->advisor($request), $studentJoinRequest);
        abort_unless($detail['status'] === StudentJoinRequest::StatusPending, 404);

        return $this->renderDashboard($request, 'join-requests', overrides: [
            'sectionTitle' => 'Decline Join Request',
            'adviserJoinRequest' => $detail,
        ]);
    }

    public function showStudentSemesterResults(Request $request, string $registrationNumber, string $sessionYears, string $semester): InertiaResponse
    {
        $student = $this->access->advisor($request)->students()
            ->with(['user', 'studentSet.currentAcademicYear'])
            ->where('registration_number', StudentProfile::registrationNumberFromRoute($registrationNumber))
            ->firstOrFail();
        $this->access->semesterByRoute($student->studentSet, $sessionYears, $semester);

        return $this->renderDashboard($request, 'students', overrides: [
            'sectionTitle' => 'Semester Results',
            'activeStudentSetId' => (int) $student->student_set_id,
            'studentRecordResults' => [
                'student' => $this->presenter->adviserStudent($student),
                'studentProfileUrl' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
                'results' => $this->results->studentResults($student, $sessionYears, $semester, true),
            ],
        ]);
    }

    public function previewStudentResultStatement(Request $request, string $registrationNumber): InertiaResponse
    {
        $student = $this->access->advisor($request)->students()
            ->where('registration_number', StudentProfile::registrationNumberFromRoute($registrationNumber))
            ->firstOrFail();

        return $this->renderDashboard($request, 'students', overrides: [
            'sectionTitle' => 'Result Statement',
            'activeStudentSetId' => (int) $student->student_set_id,
            'adviserStudents' => null,
            'studentRecordStatement' => $this->statements->build($student),
        ]);
    }

    public function showCourseGradeRoster(Request $request, string $departmentSlug, string $entrySession, string $sessionYears, string $semester, string $courseCode): InertiaResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $period = $this->access->semesterByRoute($set, $sessionYears, $semester);
        $courseOffering = $this->access->courseOfferingByRoute($set, $period, $courseCode);

        return $this->renderDashboard($request, 'courses', overrides: [
            'sectionTitle' => 'Enter Grades',
            'activeStudentSetId' => (int) $set->id,
            'adviserGradeRoster' => $this->results->courseRoster($set, $period, $courseOffering),
        ]);
    }

    public function showSetResults(Request $request, string $departmentSlug, string $entrySession): InertiaResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $filters = $request->validate([
            'session' => ['sometimes', 'regex:/^\d{8}$/'],
            'semester' => ['sometimes', 'in:first,second'],
            'all' => ['sometimes', 'in:1'],
        ]);
        abort_if(isset($filters['semester']) && ! isset($filters['session']), 404);
        abort_if(isset($filters['all']) && isset($filters['semester']), 404);

        return $this->renderDashboard($request, 'set-results', overrides: [
            'activeStudentSetId' => (int) $set->id,
            'adviserSetResults' => $this->setResults->matrix(
                $set,
                $filters['session'] ?? null,
                $filters['semester'] ?? null,
                isset($filters['all']),
                isset($filters['all']),
            ),
        ]);
    }

    private function resultFilter(Request $request, string $name): ?string
    {
        $value = $request->query($name);
        abort_unless($value === null || is_string($value), 404);

        return $value;
    }

    public function showCourseSchedule(
        Request $request,
        string $departmentSlug,
        string $entrySession,
    ): InertiaResponse {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $requestedSession = $request->query('session');
        $requestedSemester = $request->query('semester');
        abort_unless(($requestedSession === null || is_string($requestedSession))
            && ($requestedSemester === null || is_string($requestedSemester))
            && ($requestedSession !== null || $requestedSemester === null), 404);

        if ($requestedSession !== null) {
            $session = $this->access->academicSessionByYears($set, $requestedSession);

            if ($requestedSemester !== null) {
                abort_unless(in_array($requestedSemester, ['first', 'second'], true), 404);
                $session->semesterInstances()
                    ->whereHas('semester', fn (Builder $query) => $query->where('slug', $requestedSemester))
                    ->firstOrFail();
            }
        }

        $request->merge(['set' => $set->filterKey()]);

        return $this->renderDashboard($request, 'courses', overrides: [
            'sectionTitle' => 'Courses',
            'activeStudentSetId' => (int) $set->id,
        ]);
    }

    public function showCourseCatalog(
        Request $request,
        string $departmentSlug,
        string $entrySession,
    ): InertiaResponse {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);

        return $this->renderDashboard($request, 'courses', overrides: [
            'sectionTitle' => 'Course Catalog',
            'activeStudentSetId' => (int) $set->id,
            'adviserCourseCatalog' => $this->courses->catalog($set),
        ]);
    }

    public function editCatalogCourse(Request $request, string $departmentSlug, string $entrySession, string $courseCode): InertiaResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $code = Course::codeFromUrl($courseCode);
        abort_unless($code !== null, 404);
        $course = $set->courses()->where('code', $code)->firstOrFail();

        return $this->renderDashboard($request, 'courses', overrides: [
            'sectionTitle' => 'Edit Catalog Course',
            'activeStudentSetId' => (int) $set->id,
            'adviserCourseCatalog' => $this->courses->catalog($set, $course),
        ]);
    }

    public function showCourseAssignment(Request $request, string $departmentSlug, string $entrySession, string $sessionYears, string $semester, string $registrationNumber): InertiaResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $period = $this->access->semesterByRoute($set, $sessionYears, $semester);
        $student = $set->students()->where('registration_number', StudentProfile::registrationNumberFromRoute($registrationNumber))->firstOrFail();

        return $this->renderDashboard($request, 'courses', overrides: [
            'sectionTitle' => 'Assign Courses',
            'activeStudentSetId' => (int) $set->id,
            'adviserCourseAssignment' => $this->courses->assignment($set, $student, $period),
        ]);
    }

    public function showStudentSetDues(Request $request, string $departmentSlug, string $entrySession): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        abort_if($request->query('session') !== null || $request->query('semester') !== null, 404);

        return $this->renderStudentSetDues($request, $advisor, $set);
    }

    public function showStudentSetSessionDues(Request $request, string $departmentSlug, string $entrySession, string $sessionYears): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        $session = $this->access->academicSessionByYears($set, $sessionYears);

        return $this->renderStudentSetDues($request, $advisor, $set, $session);
    }

    public function createStudentSetDue(Request $request, string $departmentSlug, string $entrySession): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);

        return $this->renderStudentSetDueForm($request, $advisor, $set);
    }

    public function createStudentSetSessionDue(Request $request, string $departmentSlug, string $entrySession, string $sessionYears): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        $session = $this->access->academicSessionByYears($set, $sessionYears);

        return $this->renderStudentSetDueForm($request, $advisor, $set, $session);
    }

    public function showStudentSetSemesterDues(Request $request, string $departmentSlug, string $entrySession, string $sessionYears, string $semester): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        $session = $this->access->academicSessionByYears($set, $sessionYears);
        $selectedSemester = $this->access->semesterByRoute($set, $sessionYears, $semester);

        return $this->renderStudentSetDues($request, $advisor, $set, $session, $selectedSemester);
    }

    public function createStudentSetSemesterDue(Request $request, string $departmentSlug, string $entrySession, string $sessionYears, string $semester): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        $session = $this->access->academicSessionByYears($set, $sessionYears);
        $selectedSemester = $this->access->semesterByRoute($set, $sessionYears, $semester);

        return $this->renderStudentSetDueForm($request, $advisor, $set, $session, $selectedSemester);
    }

    private function renderStudentSetDueForm(Request $request, AdvisorProfile $advisor, StudentSet $set, ?AcademicSession $session = null, ?AcademicSessionSemester $semester = null): InertiaResponse
    {
        return $this->renderDashboard($request, 'student-dues', overrides: [
            'activeStudentSetId' => (int) $set->id,
            'adviserDueForm' => $this->dues->dueForm($advisor, setPublicId: $set->public_id,
                sessionPublicId: $session?->public_id, semesterPublicId: $semester?->public_id),
        ]);
    }

    private function renderStudentSetDues(Request $request, AdvisorProfile $advisor, StudentSet $set, ?AcademicSession $session = null, ?AcademicSessionSemester $semester = null): InertiaResponse
    {
        $request->merge(['set' => $set->filterKey()]);

        return $this->renderDashboard($request, 'student-dues', overrides: [
            'activeStudentSetId' => (int) $set->id,
            'adviserDues' => $this->dues->adviserDues($advisor, $set, $session, $semester),
        ]);
    }

    public function createAcademicSession(
        Request $request,
        string $departmentSlug,
        string $entrySession,
    ): InertiaResponse {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);

        return $this->renderDashboard($request, 'student-sets', overrides: [
            'sectionTitle' => 'Add Academic Session',
            'activeStudentSetId' => (int) $set->id,
            'adviserAcademicSessionForm' => $this->courses->sessionForm($set),
        ]);
    }

    public function editAcademicSession(
        Request $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
    ): InertiaResponse {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $academicSession = $this->access->academicSessionByYears($set, $sessionYears);

        return $this->renderDashboard($request, 'student-sets', overrides: [
            'sectionTitle' => 'Edit Academic Session',
            'activeStudentSetId' => (int) $set->id,
            'adviserAcademicSessionForm' => $this->courses->sessionForm($set, $academicSession),
        ]);
    }

    public function createCourseOffering(
        Request $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
        string $semester,
    ): InertiaResponse {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $academicSessionSemester = $this->access->semesterByRoute($set, $sessionYears, $semester);

        return $this->renderDashboard($request, 'courses', overrides: [
            'sectionTitle' => 'Add Course',
            'activeStudentSetId' => (int) $set->id,
            'adviserCourseOfferingForm' => $this->courses->offeringForm($set, $academicSessionSemester),
        ]);
    }

    public function editCourseOffering(
        Request $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
        string $semester,
        string $courseCode,
    ): InertiaResponse {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $period = $this->access->semesterByRoute($set, $sessionYears, $semester);
        $courseOffering = $this->access->courseOfferingByRoute($set, $period, $courseCode);

        return $this->renderDashboard($request, 'courses', overrides: [
            'sectionTitle' => 'Edit Course',
            'activeStudentSetId' => (int) $set->id,
            'adviserCourseOfferingForm' => $this->courses->offeringForm(
                $set,
                $period,
                $courseOffering,
            ),
        ]);
    }

    public function paymentHistory(Request $request): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $request->validate(['set' => ['sometimes', 'regex:/^[a-z0-9-]+-\d{8}$/'], 'from' => 'sometimes|date_format:Y-m-d', 'to' => 'sometimes|date_format:Y-m-d|after_or_equal:from']);

        return $this->renderDashboard($request, 'student-dues', overrides: [
            'sectionTitle' => 'Payment History',
            'activeStudentSetId' => null,
            'adviserPaymentHistory' => $this->activity->payments($advisor, $request),
            'adviserDues' => null,
        ]);
    }

    public function assistantConversation(Request $request, AssistantConversation $assistantConversation): InertiaResponse
    {
        $user = $this->access->user($request);
        $owner = $user->isAdviser() ? $this->access->advisor($request) : $this->access->student($request);
        $ownerColumn = $user->isAdviser() ? 'advisor_profile_id' : 'student_profile_id';
        abort_unless((int) $assistantConversation->{$ownerColumn} === (int) $owner->id, 404);

        return $this->renderDashboard($request, 'assistant', overrides: [
            'adviserAssistant' => $this->assistantPayload($owner, $assistantConversation),
        ]);
    }

    public function editAdviserDue(Request $request, Due $due): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsDue($advisor, $due);
        abort_if($due->status === Due::StatusArchived, 404);

        return $this->renderDashboard($request, 'student-dues', overrides: [
            'adviserDueForm' => $this->dues->dueForm($advisor, $due),
            'activeStudentSetId' => (int) $due->student_set_id,
        ]);
    }

    public function showAdviserDue(Request $request, Due $due): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsDue($advisor, $due);

        return $this->renderDashboard($request, 'student-dues', overrides: [
            'adviserDueDetail' => $this->dues->dueDetail($due),
            'activeStudentSetId' => (int) $due->student_set_id,
        ]);
    }

    public function createStudentDueWaiver(
        Request $request,
        Due $due,
        StudentDueAssignment $studentDueAssignment,
    ): InertiaResponse {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsAssignment($advisor, $due, $studentDueAssignment);
        abort_unless($studentDueAssignment->status === StudentDueAssignment::StatusPending, 404);

        return $this->renderDashboard($request, 'student-dues', overrides: [
            'adviserDueWaiverForm' => $this->dues->waiverForm($due, $studentDueAssignment),
            'activeStudentSetId' => (int) $due->student_set_id,
        ]);
    }

    public function createAdviserPaymentAccount(Request $request): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $form = $this->dues->paymentAccountForm($advisor);

        return $this->renderDashboard($request, 'settings', overrides: [
            'sectionTitle' => $form['accounts'] === [] ? 'Connect Payout Account' : 'Change Payout Account',
            'adviserPaymentAccountForm' => $form,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function renderDashboard(
        Request $request,
        string $section,
        array $overrides = [],
    ): InertiaResponse {
        $user = $this->access->user($request);
        $role = $this->dashboardRole($user);
        abort_unless(in_array($section, self::ROLE_SECTIONS[$role], true), 404);
        $advisor = $this->access->advisorFor($user);
        $student = $user->studentProfile;
        $sets = $this->studentSetsFor($advisor);
        $selectedSet = $sets->first(fn (StudentSet $set): bool => $set->filterKey() === $request->query('set'));
        $setPayloads = $sets->map(fn (StudentSet $set): array => $this->presenter->studentSet($set))->values()->all();
        $studentDetail = isset($overrides['studentRecord']) || isset($overrides['studentRecordResults']) || isset($overrides['studentRecordStatement']);
        $courseDetail = isset($overrides['adviserGradeRoster']) || isset($overrides['adviserCourseCatalog'])
            || isset($overrides['adviserCourseAssignment']) || isset($overrides['adviserCourseOfferingForm']);
        $dueDetail = isset($overrides['adviserPaymentHistory']) || isset($overrides['adviserDueForm'])
            || isset($overrides['adviserDueDetail']) || isset($overrides['adviserDueWaiverForm']);

        $dashboard = [
            'role' => $role,
            'roleLabel' => $role === 'adviser' ? 'Course Adviser' : 'Student',
            'section' => $section,
            'sectionTitle' => match (true) {
                $role === 'student' && $section === 'results' => 'Results',
                $role === 'student' && $section === 'courses' => 'My Courses',
                default => self::SECTION_TITLES[$section],
            },
            'advisedSets' => $setPayloads,
            'activeStudentSetId' => $selectedSet?->id,
            'adviserHome' => $role === 'adviser' && $section === 'home' ? $this->activity->home($advisor) : null,
            'adviserJoinRequest' => null,
            'adviserPaymentHistory' => null,
            'adviserAssistant' => ! array_key_exists('adviserAssistant', $overrides) && $section === 'assistant'
                ? $this->assistantPayload($role === 'adviser' ? $advisor : $student)
                : null,
            'adviserStudentSets' => $role === 'adviser' && $section === 'student-sets'
                ? $this->presenter->adviserStudentSets($request->routeIs('student-sets.create'))
                : null,
            'adviserStudents' => $role === 'adviser' && $section === 'students' && ! $studentDetail
                ? $this->presenter->adviserStudents($advisor, $sets, $selectedSet?->id)
                : null,
            'adviserCourseSchedules' => $role === 'adviser' && $section === 'courses' && ! $courseDetail
                ? $this->courses->offerings($request, $sets) : null,
            'adviserCourseCatalog' => null,
            'adviserCourseAssignment' => null,
            'adviserSetResults' => null,
            'adviserAcademicSessionForm' => null,
            'adviserCourseOfferingForm' => null,
            'adviserGradeRoster' => null,
            'adviserDues' => ! array_key_exists('adviserDues', $overrides) && ! $dueDetail
                && $role === 'adviser' && $section === 'student-dues'
                ? $this->dues->adviserDues($advisor, $selectedSet ?? $sets->first())
                : null,
            'adviserDueForm' => null,
            'adviserDueDetail' => null,
            'adviserDueWaiverForm' => null,
            'adviserPaymentSettings' => $role === 'adviser' && $section === 'settings' && ! isset($overrides['adviserPaymentAccountForm'])
                ? $this->dues->paymentSettings($advisor) : null,
            'adviserPaymentAccountForm' => null,
            'adviserProfile' => $role === 'adviser' && $section === 'settings' && ! isset($overrides['adviserPaymentAccountForm'])
                ? $this->presenter->adviserProfile($advisor) : null,
            'studentRecord' => null,
            'studentRecordResults' => null,
            'studentRecordStatement' => null,
            'studentSetSettings' => null,
            'student' => $student instanceof StudentProfile ? $this->presenter->studentHeader($student) : null,
            'studentProfile' => $role === 'student' && $section === 'profile-settings' ? $this->presenter->studentProfile($student) : null,
            'studentHome' => $role === 'student' && $section === 'home'
                ? $this->courses->studentHome($student) + $this->results->studentHomeStatus($student) + [
                    'pendingDues' => $this->dues->studentPendingDues($student),
                ] : null,
            'studentDues' => $role === 'student' && $section === 'dues' ? $this->dues->studentDues($student) : null,
            'studentCourses' => $role === 'student' && $section === 'courses'
                ? $this->courses->studentCourses($student, $this->resultFilter($request, 'session'), $this->resultFilter($request, 'semester')) : null,
            'studentResults' => $role === 'student' && $section === 'results'
                ? $this->results->studentResults($student, $this->resultFilter($request, 'session'), $this->resultFilter($request, 'semester'), false)
                : null,
        ];

        return Inertia::render('dashboard', ['dashboard' => array_replace($dashboard, $overrides)]);
    }

    private function dashboardRole(User $user): string
    {
        return $user->account_type === AccountType::Adviser ? 'adviser' : 'student';
    }

    private function assistantPayload(AdvisorProfile|StudentProfile $owner, ?AssistantConversation $selected = null): array
    {
        $lockAt = $owner instanceof StudentProfile ? $this->resultAccess->latestResultLockAt($owner) : null;
        abort_if($selected !== null && $lockAt !== null && $selected->created_at->lessThanOrEqualTo($lockAt), 404);
        $history = $owner->assistantConversations()
            ->when($lockAt, fn ($query) => $query->where('created_at', '>', $lockAt))
            ->latest('updated_at')->limit(50)->get();
        $selected?->load('messages');

        return [
            'available' => $this->assistant->available(),
            'conversations' => $history->map(fn (AssistantConversation $conversation): array => [
                'id' => $conversation->public_id,
                'title' => $conversation->title,
                'updatedAt' => $conversation->updated_at->toFormattedDateString(),
            ])->all(),
            'selectedId' => $selected?->public_id,
            'messages' => $selected?->messages->map(fn ($message): array => [
                'id' => (int) $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'html' => $message->role === 'assistant' && filled($message->content)
                    ? Str::markdown($message->content, ['html_input' => 'strip', 'allow_unsafe_links' => false])
                    : null,
                'status' => $message->status,
                'error' => $message->error_message,
                'sources' => $message->sources ?? [],
            ])->all() ?? [],
        ];
    }

    /** @return Collection<int, StudentSet> */
    private function studentSetsFor(AdvisorProfile $advisor): Collection
    {
        return $advisor->studentSets()
            ->with('currentAcademicYear')
            ->withCount('students')
            ->get()
            ->sortByDesc(fn (StudentSet $set): int => $set->entry_start_year)
            ->values();
    }
}
