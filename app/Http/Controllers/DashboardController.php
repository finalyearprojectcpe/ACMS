<?php

namespace App\Http\Controllers;

use App\AccountType;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Due;
use App\Models\Semester;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\CourseDashboardData;
use App\Services\DashboardAccess;
use App\Services\DashboardPresenter;
use App\Services\DueDashboardData;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    private const array ROLE_SECTIONS = [
        'adviser' => ['home', 'students', 'courses', 'course-registration', 'dues', 'payment-settings', 'profile-settings'],
        'student' => ['home', 'dues', 'course-registration', 'profile-settings'],
    ];

    private const array SECTION_TITLES = [
        'home' => 'Home',
        'students' => 'Students',
        'courses' => 'Course Offerings',
        'course-registration' => 'Course Registrations',
        'dues' => 'Dues',
        'payment-settings' => 'Paystack Settings',
        'profile-settings' => 'Profile Settings',
    ];

    public function __construct(
        private readonly DashboardAccess $access,
        private readonly AcademicContextService $academicContext,
        private readonly DashboardPresenter $presenter,
        private readonly CourseDashboardData $courses,
        private readonly DueDashboardData $dues,
    ) {}

    public function home(Request $request): InertiaResponse
    {
        return $this->renderDashboard($request, 'home');
    }

    public function section(Request $request, string $section): InertiaResponse
    {
        return $this->renderDashboard($request, $section);
    }

    public function createAdvisedStudent(Request $request): InertiaResponse
    {
        $advisor = $this->access->advisor($request);

        return $this->renderDashboard($request, 'students', overrides: [
            'adviserStudentForm' => $this->presenter->studentForm($advisor, 'create'),
        ]);
    }

    public function importAdvisedStudents(Request $request): InertiaResponse
    {
        $advisor = $this->access->advisor($request);

        return $this->renderDashboard($request, 'students', overrides: [
            'adviserStudentForm' => $this->presenter->studentForm($advisor, 'import'),
        ]);
    }

    public function showStudentProfile(Request $request, string $registrationNumber): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $student = $advisor->students()
            ->with(['user', 'currentLevel', 'studentSet.entryAcademicSession'])
            ->where('registration_number', $registrationNumber)
            ->firstOrFail();

        return $this->renderDashboard($request, 'students', overrides: [
            'studentRecord' => $this->presenter->studentRecord($student),
        ]);
    }

    public function createCourseOffering(Request $request, string $level, string $semester): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->selectedStudentSet($request, $advisor);
        $academicYear = $this->resolveLevel($level, $set);
        $semesterModel = $this->resolveSemester($semester);
        $session = $this->requiredSessionForSetYear($set, $academicYear);

        return $this->renderDashboard($request, 'courses', overrides: [
            'adviserCourseOfferingForm' => $this->courses->offeringForm($set, $session, $academicYear, $semesterModel),
        ]);
    }

    public function editCourseOffering(
        Request $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): InertiaResponse {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSet($advisor, (int) $offering->student_set_id);
        $year = $this->resolveLevel($level, $set);
        $semesterModel = $this->resolveSemester($semester);
        $session = $this->requiredSessionForSetYear($set, $year);
        $slot = $this->yearSemester($year, $semesterModel);
        abort_unless(
            (int) $offering->student_set_id === (int) $set->id
                && (int) $offering->academic_session_id === (int) $session->id
                && (int) $offering->academic_year_semester_id === (int) $slot->id,
            404,
        );

        return $this->renderDashboard($request, 'courses', overrides: [
            'adviserCourseOfferingForm' => $this->courses->offeringForm($set, $session, $year, $semesterModel, $offering),
        ]);
    }

    public function showCourseRegistrationReview(Request $request, CourseRegistration $courseRegistration): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsRegistration($advisor, $courseRegistration);

        return $this->renderDashboard($request, 'course-registration', overrides: [
            'adviserCourseRegistration' => $this->courses->adviserRegistrations($advisor, $courseRegistration),
        ]);
    }

    public function createAdviserDue(Request $request): InertiaResponse
    {
        $advisor = $this->access->advisor($request);

        return $this->renderDashboard($request, 'dues', overrides: [
            'adviserDueForm' => $this->dues->dueForm($advisor),
        ]);
    }

    public function editAdviserDue(Request $request, Due $due): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsDue($advisor, $due);
        abort_if($due->status === Due::StatusArchived, 404);

        return $this->renderDashboard($request, 'dues', overrides: [
            'adviserDueForm' => $this->dues->dueForm($advisor, $due),
        ]);
    }

    public function showAdviserDue(Request $request, Due $due): InertiaResponse
    {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsDue($advisor, $due);

        return $this->renderDashboard($request, 'dues', overrides: [
            'adviserDueDetail' => $this->dues->dueDetail($due),
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

        return $this->renderDashboard($request, 'dues', overrides: [
            'adviserDueWaiverForm' => $this->dues->waiverForm($due, $studentDueAssignment),
        ]);
    }

    public function createAdviserPaymentAccount(Request $request): InertiaResponse
    {
        $advisor = $this->access->advisor($request);

        return $this->renderDashboard($request, 'payment-settings', overrides: [
            'adviserPaymentAccountForm' => $this->dues->paymentAccountForm($advisor),
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
        $levels = $this->presenter->levelsFor($advisor);
        $sets = $advisor->studentSets()->with('entryAcademicSession')->orderByDesc('entry_academic_session_id')->get();
        $setPayloads = $sets->map(fn (StudentSet $set): array => $this->presenter->studentSet($set))->values()->all();

        $dashboard = [
            'role' => $role,
            'roleLabel' => $role === 'adviser' ? 'Course Adviser' : 'Student',
            'section' => $section,
            'sectionTitle' => self::SECTION_TITLES[$section],
            'levelSlug' => null,
            'level' => null,
            'levels' => $levels->map(fn (AcademicYear $level): array => $this->presenter->level($level))->all(),
            'courseScheduleLevels' => $levels->map(fn (AcademicYear $level): array => $this->presenter->level($level))->all(),
            'advisedSets' => $setPayloads,
            'programme' => $this->presenter->programme($advisor),
            'adviserHome' => $role === 'adviser' && $section === 'home' ? $this->presenter->adviserHome($advisor, $sets, $levels) : null,
            'adviserStudents' => $role === 'adviser' && $section === 'students' ? $this->presenter->adviserStudents($advisor, $sets) : null,
            'adviserStudentForm' => null,
            'adviserCourseSchedules' => $role === 'adviser' && $section === 'courses' ? $this->courses->offerings($request, $sets, $levels) : null,
            'adviserCourseOfferingForm' => null,
            'adviserCourseRegistration' => $role === 'adviser' && $section === 'course-registration' ? $this->courses->adviserRegistrations($advisor) : null,
            'adviserDues' => $role === 'adviser' && $section === 'dues' ? $this->dues->adviserDues($advisor, $sets) : null,
            'adviserDueForm' => null,
            'adviserDueDetail' => null,
            'adviserDueWaiverForm' => null,
            'adviserPaymentSettings' => $role === 'adviser' && $section === 'payment-settings' ? $this->dues->paymentSettings($advisor) : null,
            'adviserPaymentAccountForm' => null,
            'adviserSettings' => $role === 'adviser' && $section === 'profile-settings' ? $this->presenter->adviserSettings($request, $advisor, $sets) : null,
            'studentRecord' => null,
            'student' => $student instanceof StudentProfile ? $this->presenter->studentHeader($student) : null,
            'studentProfile' => $role === 'student' && $section === 'profile-settings' ? $this->presenter->studentProfile($student) : null,
            'studentHome' => $role === 'student' && $section === 'home' ? $this->courses->studentHome($student) : null,
            'studentDues' => $role === 'student' && $section === 'dues' ? $this->dues->studentDues($student) : null,
            'studentCourseRegistration' => $role === 'student' && $section === 'course-registration' ? $this->courses->studentRegistration($student) : null,
        ];

        return Inertia::render('dashboard', ['dashboard' => array_replace($dashboard, $overrides)]);
    }

    private function dashboardRole(User $user): string
    {
        return $user->account_type === AccountType::Adviser ? 'adviser' : 'student';
    }

    private function resolveLevel(string $slug, StudentSet $set): AcademicYear
    {
        return AcademicYear::query()->where('slug', $slug)->where('year_number', '<=', $set->minimum_years_to_degree)->firstOrFail();
    }

    private function resolveSemester(string $slug): Semester
    {
        return Semester::query()->where('slug', $slug)->firstOrFail();
    }

    private function yearSemester(AcademicYear $year, Semester $semester): AcademicYearSemester
    {
        return AcademicYearSemester::query()->whereBelongsTo($year)->whereBelongsTo($semester)->firstOrFail();
    }

    private function requiredSessionForSetYear(StudentSet $set, AcademicYear $year): AcademicSession
    {
        $session = $this->academicContext->sessionForSetYear($set, $year);

        if (! ($session instanceof AcademicSession)) {
            throw ValidationException::withMessages([
                'session' => 'Create the academic session for this student set and level before continuing.',
            ]);
        }

        return $session;
    }
}
