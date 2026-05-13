<?php

namespace App\Http\Controllers;

use App\AccountType;
use App\Http\Requests\StaffCourseOfferingRequest;
use App\Http\Requests\StaffCourseTimetableSlotRequest;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseTimetableSlot;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Level;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    private const array ROLE_SECTIONS = [
        'faculty_head' => ['home', 'departments', 'staff', 'courses', 'academic-sessions', 'settings'],
        'staff' => ['home', 'students', 'courses', 'grades', 'dues', 'reports', 'approvals', 'settings'],
        'student' => ['home', 'results', 'dues', 'course-schedules', 'profile'],
        'hod' => ['home', 'staff-assignments', 'student-sets', 'courses', 'grades', 'reports', 'settings'],
    ];

    private const array SECTION_TITLES = [
        'home' => 'Home',
        'students' => 'Students',
        'courses' => 'Course Schedules',
        'grades' => 'Grades',
        'dues' => 'Dues',
        'profile' => 'Profile',
        'reports' => 'Reports',
        'approvals' => 'Approvals',
        'settings' => 'Settings',
        'results' => 'Results',
        'course-schedules' => 'Course Schedules',
        'staff-assignments' => 'Staff Assignments',
        'student-sets' => 'Student Sets',
        'departments' => 'Departments',
        'staff' => 'Staff',
        'academic-sessions' => 'Academic Sessions',
    ];

    private const array TIMETABLE_SLOTS = [
        ['day' => 'Monday', 'time' => '08:00 - 10:00', 'venue' => 'ETF 204'],
        ['day' => 'Monday', 'time' => '10:00 - 12:00', 'venue' => 'CPE Lab 1'],
        ['day' => 'Tuesday', 'time' => '08:00 - 10:00', 'venue' => 'SESET 102'],
        ['day' => 'Tuesday', 'time' => '12:00 - 14:00', 'venue' => 'ETF 301'],
        ['day' => 'Wednesday', 'time' => '09:00 - 11:00', 'venue' => 'CPE Studio'],
        ['day' => 'Wednesday', 'time' => '14:00 - 16:00', 'venue' => 'CPE Lab 2'],
        ['day' => 'Thursday', 'time' => '08:00 - 10:00', 'venue' => 'ETF 204'],
        ['day' => 'Thursday', 'time' => '11:00 - 13:00', 'venue' => 'SESET 201'],
        ['day' => 'Friday', 'time' => '09:00 - 11:00', 'venue' => 'ETF 301'],
        ['day' => 'Friday', 'time' => '13:00 - 15:00', 'venue' => 'CPE Studio'],
    ];

    private const array WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    private const array RESULT_COURSES = [
        '100-level' => [
            'harmattan' => [
                ['GST 101', 'Use of English I', 2],
                ['MTH 101', 'Engineering Mathematics I', 3],
                ['PHY 101', 'General Physics I', 3],
                ['CHM 101', 'General Chemistry I', 3],
                ['CPE 101', 'Introduction to Computer Engineering', 2],
            ],
            'rain' => [
                ['GST 102', 'Use of English II', 2],
                ['MTH 102', 'Engineering Mathematics II', 3],
                ['PHY 102', 'General Physics II', 3],
                ['CHM 102', 'General Chemistry II', 3],
                ['CPE 102', 'Computer Workshop Practice', 2],
            ],
        ],
        '200-level' => [
            'harmattan' => [
                ['MTH 207', 'Engineering Mathematics III', 3],
                ['CPE 201', 'Digital Logic Design', 3],
                ['CPE 203', 'Circuit Theory', 3],
                ['EEE 201', 'Electrical Machines I', 2],
                ['CSC 201', 'Computer Programming I', 3],
            ],
            'rain' => [
                ['MTH 208', 'Engineering Mathematics IV', 3],
                ['CPE 202', 'Computer Architecture Fundamentals', 3],
                ['CPE 204', 'Signals and Systems', 3],
                ['EEE 202', 'Electronic Devices', 3],
                ['CSC 202', 'Data Structures', 3],
            ],
        ],
        '300-level' => [
            'harmattan' => [
                ['CPE 301', 'Microprocessor Systems', 3],
                ['CPE 303', 'Operating Systems', 3],
                ['CPE 305', 'Data Structures and Algorithms', 3],
                ['EEE 301', 'Electronic Circuit Design', 3],
                ['MTH 305', 'Numerical Methods', 2],
            ],
            'rain' => [
                ['CPE 302', 'Embedded Systems Design', 3],
                ['CPE 304', 'Database Systems', 3],
                ['CPE 306', 'Software Engineering', 3],
                ['EEE 302', 'Control Systems Engineering', 3],
                ['CPE 308', 'Computer Networks', 2],
            ],
        ],
    ];

    private const array RESULT_SCORES = [
        '100-level' => [
            'harmattan' => [
                'GST 101' => 76,
                'MTH 101' => 68,
                'PHY 101' => 63,
                'CHM 101' => 59,
                'CPE 101' => 82,
            ],
            'rain' => [
                'GST 102' => 74,
                'MTH 102' => 62,
                'PHY 102' => 58,
                'CHM 102' => 55,
                'CPE 102' => 79,
            ],
        ],
        '200-level' => [
            'harmattan' => [
                'MTH 207' => 61,
                'CPE 201' => 73,
                'CPE 203' => 57,
                'EEE 201' => 48,
                'CSC 201' => 69,
            ],
            'rain' => [
                'MTH 208' => 64,
                'CPE 202' => 71,
                'CPE 204' => 53,
                'EEE 202' => 46,
                'CSC 202' => 67,
            ],
        ],
        '300-level' => [
            'harmattan' => [
                'CPE 301' => 74,
                'CPE 303' => 67,
                'CPE 305' => 58,
                'EEE 301' => 62,
                'MTH 305' => 71,
            ],
            'rain' => [
                'CPE 302' => 69,
                'CPE 304' => 38,
                'CPE 306' => 64,
                'EEE 302' => 52,
                'CPE 308' => 73,
            ],
        ],
    ];

    public function home(Request $request): InertiaResponse
    {
        return $this->renderDashboard($request, 'home');
    }

    public function section(Request $request, string $section): InertiaResponse
    {
        return $this->renderDashboard($request, $section);
    }

    public function level(Request $request, string $section, string $level): InertiaResponse
    {
        return $this->renderDashboard($request, $section, $level);
    }

    public function createCourseOffering(Request $request, string $level, string $semester): InertiaResponse
    {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);

        return $this->renderDashboard($request, 'courses', $level, [
            'staffCourseOfferingForm' => $this->courseOfferingFormPayload($department, $levelModel, $semesterModel),
        ]);
    }

    public function storeCourseOffering(
        StaffCourseOfferingRequest $request,
        string $level,
        string $semester,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);

        $validated = $request->validated();
        $prerequisiteIds = $this->validatedPrerequisiteIds(
            $department,
            $levelModel,
            $semesterModel,
            $validated['prerequisite_course_ids'] ?? [],
        );
        $course = Course::query()->updateOrCreate(
            ['code' => $validated['code']],
            [
                'title' => $validated['title'],
                'slug' => Str::slug($validated['code'].' '.$validated['title']),
                'credit_units' => (int) $validated['credit_units'],
            ],
        );

        $department->courseOfferings()->updateOrCreate(
            [
                'course_id' => $course->id,
                'level_id' => $levelModel->id,
                'semester_id' => $semesterModel->id,
            ],
            ['is_mandatory' => (bool) $validated['is_mandatory']],
        );

        $course->prerequisites()->sync($prerequisiteIds);

        return to_route('dashboard.level', [
            'section' => 'courses',
            'level' => $levelModel->slug,
        ]);
    }

    public function editCourseOffering(
        Request $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): InertiaResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);

        $offering = $this->editableCourseOffering($department, $offering, $levelModel, $semesterModel);

        return $this->renderDashboard($request, 'courses', $level, [
            'staffCourseOfferingForm' => $this->courseOfferingFormPayload(
                $department,
                $levelModel,
                $semesterModel,
                $offering,
            ),
        ]);
    }

    public function updateCourseOffering(
        StaffCourseOfferingRequest $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);

        $offering = $this->editableCourseOffering($department, $offering, $levelModel, $semesterModel);
        $validated = $request->validated();
        $prerequisiteIds = $this->validatedPrerequisiteIds(
            $department,
            $levelModel,
            $semesterModel,
            $validated['prerequisite_course_ids'] ?? [],
        );
        $course = Course::query()->updateOrCreate(
            ['code' => $validated['code']],
            [
                'title' => $validated['title'],
                'slug' => Str::slug($validated['code'].' '.$validated['title']),
                'credit_units' => (int) $validated['credit_units'],
            ],
        );

        $duplicateOfferingExists = CourseOffering::query()
            ->where('owner_type', Department::class)
            ->where('owner_id', $department->id)
            ->where('level_id', $levelModel->id)
            ->where('semester_id', $semesterModel->id)
            ->where('course_id', $course->id)
            ->whereKeyNot($offering->id)
            ->exists();

        if ($duplicateOfferingExists) {
            throw ValidationException::withMessages([
                'code' => 'This course is already on the schedule for the selected level and semester.',
            ]);
        }

        $offering->forceFill([
            'course_id' => $course->id,
            'is_mandatory' => (bool) $validated['is_mandatory'],
        ])->save();

        $course->prerequisites()->sync($prerequisiteIds);

        return to_route('dashboard.level', [
            'section' => 'courses',
            'level' => $levelModel->slug,
        ]);
    }

    public function courseTimetable(
        Request $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): InertiaResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);

        $offering = $this->manageableCourseOffering($department, $offering, $levelModel, $semesterModel);

        return $this->renderDashboard($request, 'courses', $level, [
            'staffCourseTimetable' => $this->courseTimetablePayload($levelModel, $semesterModel, $offering),
        ]);
    }

    public function storeCourseTimetableSlot(
        StaffCourseTimetableSlotRequest $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);

        $offering = $this->manageableCourseOffering($department, $offering, $levelModel, $semesterModel);
        $validated = $request->validated();

        $this->ensureTimetableSlotDoesNotClash($department, $offering, $validated);

        $offering->timetableSlots()->create($validated);

        return to_route('dashboard.courses.timetable', [
            'level' => $levelModel->slug,
            'semester' => $semesterModel->slug,
            'offering' => $offering->id,
        ]);
    }

    public function updateCourseTimetableSlot(
        StaffCourseTimetableSlotRequest $request,
        string $level,
        string $semester,
        CourseOffering $offering,
        CourseTimetableSlot $slot,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);

        $offering = $this->manageableCourseOffering($department, $offering, $levelModel, $semesterModel);
        abort_unless((int) $slot->course_offering_id === (int) $offering->id, 404);

        $validated = $request->validated();

        $this->ensureTimetableSlotDoesNotClash($department, $offering, $validated, $slot);

        $slot->update($validated);

        return to_route('dashboard.courses.timetable', [
            'level' => $levelModel->slug,
            'semester' => $semesterModel->slug,
            'offering' => $offering->id,
        ]);
    }

    public function deleteCourseTimetableSlot(
        Request $request,
        string $level,
        string $semester,
        CourseOffering $offering,
        CourseTimetableSlot $slot,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);

        $offering = $this->manageableCourseOffering($department, $offering, $levelModel, $semesterModel);
        abort_unless((int) $slot->course_offering_id === (int) $offering->id, 404);

        $slot->delete();

        return to_route('dashboard.courses.timetable', [
            'level' => $levelModel->slug,
            'semester' => $semesterModel->slug,
            'offering' => $offering->id,
        ]);
    }

    private function renderDashboard(
        Request $request,
        string $section,
        ?string $level = null,
        array $dashboardOverrides = [],
    ): InertiaResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing([
            'staffProfile.department.faculty',
            'staffProfile.headedFaculty',
            'studentProfile.currentLevel',
            'studentProfile.department.faculty',
        ]);

        $role = $this->dashboardRole($user);
        $levelModel = $level === null ? null : Level::query()->where('slug', $level)->first();

        abort_if($level !== null && $levelModel === null, 404);
        abort_unless($this->isAllowedRoute($user, $role, $section, $levelModel), 404);

        $dashboard = [
            'role' => $role,
            'roleLabel' => $this->roleLabel($role),
            'section' => $section,
            'sectionTitle' => $this->sectionTitle($section, $levelModel),
            'levelSlug' => $levelModel?->slug,
            'level' => $levelModel?->name,
            'levels' => $this->levelsFor($user, $role),
            'courseScheduleLevels' => $this->courseScheduleLevelsFor($user, $role),
            'department' => $this->departmentFor($user)?->only(['name', 'abbreviation']),
            'faculty' => $this->facultyFor($user, $role)?->only(['name', 'abbreviation']),
            'staffHome' => $this->staffHomePayload($user, $role, $section),
            'staffStudents' => $this->staffStudentsPayload($user, $role, $section),
            'staffCourseSchedules' => $this->staffCourseSchedulesPayload($user, $role, $section, $levelModel),
            'staffCourseOfferingForm' => null,
            'staffCourseTimetable' => null,
            'student' => $this->studentPayload($user, $role),
            'studentProfile' => $this->studentProfilePayload($user, $role, $section),
            'studentHome' => $this->studentHomePayload($user, $role, $section),
            'studentResults' => $this->studentResultsPayload($user, $role, $section, $levelModel),
            'studentDues' => $this->studentDuesPayload($user, $role, $section),
            'studentCourseSchedules' => $this->studentCourseSchedulesPayload($user, $role, $section),
        ];

        return Inertia::render('dashboard', [
            'dashboard' => array_merge($dashboard, $dashboardOverrides),
        ]);
    }

    private function dashboardRole(User $user): string
    {
        if ($user->account_type === AccountType::Student) {
            return 'student';
        }

        $staffProfile = $user->staffProfile;

        if ($staffProfile?->headedFaculty !== null) {
            return 'faculty_head';
        }

        if (
            $staffProfile !== null
            && $staffProfile->department !== null
            && (int) $staffProfile->department->hod_staff_profile_id === (int) $staffProfile->getKey()
        ) {
            return 'hod';
        }

        return 'staff';
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'faculty_head' => 'Faculty Head',
            'hod' => 'Head of Department',
            'staff' => 'Staff',
            'student' => 'Student',
        };
    }

    private function isAllowedRoute(User $user, string $role, string $section, ?Level $level): bool
    {
        if (! in_array($section, self::ROLE_SECTIONS[$role], true)) {
            return false;
        }

        if ($level !== null) {
            $allowedLevels = $section === 'courses'
                ? $this->courseScheduleLevelsFor($user, $role)
                : $this->levelsFor($user, $role);

            if (! collect($allowedLevels)->contains('slug', $level->slug)) {
                return false;
            }

            return match ($section) {
                'courses', 'grades' => in_array($role, ['staff', 'hod'], true),
                'results' => $role === 'student',
                'dues' => $role === 'staff',
                default => false,
            };
        }

        return true;
    }

    private function sectionTitle(string $section, ?Level $level): string
    {
        $title = self::SECTION_TITLES[$section];

        if ($level !== null) {
            return $level->name.' '.$title;
        }

        return $title;
    }

    private function departmentFor(User $user): ?Department
    {
        if ($user->account_type === AccountType::Student) {
            return $user->studentProfile?->department;
        }

        return $user->staffProfile?->department;
    }

    private function facultyFor(User $user, string $role): ?Faculty
    {
        if ($role !== 'faculty_head') {
            return null;
        }

        return $user->staffProfile?->headedFaculty;
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    private function levelsFor(User $user, string $role): array
    {
        $department = $this->departmentFor($user);

        if ($department === null || $role === 'faculty_head') {
            return [];
        }

        if ($role === 'student') {
            return $this->studentLevelsFor($user);
        }

        if ($role === 'staff' && $user->staffProfile !== null) {
            $assignedLevels = $user->staffProfile
                ->assignedLevels()
                ->wherePivot('department_id', $department->id)
                ->orderBy('levels.id')
                ->get();

            if ($assignedLevels->isNotEmpty()) {
                return $assignedLevels
                    ->map(fn (Level $level): array => $this->levelPayload($level))
                    ->values()
                    ->all();
            }
        }

        return $department->visibleLevels()
            ->map(fn (Level $level): array => $this->levelPayload($level))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    private function courseScheduleLevelsFor(User $user, string $role): array
    {
        $department = $this->departmentFor($user);

        if ($department === null || in_array($role, ['student', 'faculty_head'], true)) {
            return [];
        }

        if ($role === 'staff' && $user->staffProfile !== null) {
            $assignedLevels = $this->assignedLevelsForStaff($user->staffProfile, $department);
            $highestLevelId = $assignedLevels->max('id');

            if ($highestLevelId !== null) {
                return Level::query()
                    ->where('id', '<=', $highestLevelId)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Level $level): array => $this->levelPayload($level))
                    ->values()
                    ->all();
            }
        }

        return $department->visibleLevels()
            ->map(fn (Level $level): array => $this->levelPayload($level))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    private function studentLevelsFor(User $user): array
    {
        $currentLevel = $user->studentProfile?->currentLevel;

        if ($currentLevel === null) {
            return [];
        }

        return Level::query()
            ->where('id', '<=', $currentLevel->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Level $level): array => $this->levelPayload($level))
            ->values()
            ->all();
    }

    /**
     * @return array{registrationNumber: string, currentLevel: array{id: int, title: string, slug: string}|null}|null
     */
    private function studentPayload(User $user, string $role): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $studentProfile === null) {
            return null;
        }

        return [
            'registrationNumber' => $studentProfile->registration_number,
            'currentLevel' => $studentProfile->currentLevel === null
                ? null
                : $this->levelPayload($studentProfile->currentLevel),
        ];
    }

    /**
     * @return array{
     *     name: array{full: string, surname: string, otherNames: string},
     *     email: string,
     *     avatar: string|null,
     *     registrationNumber: string,
     *     cgpa: string,
     *     faculty: array{name: string, abbreviation: string}|null,
     *     department: array{name: string, abbreviation: string}|null,
     *     currentLevel: array{id: int, title: string, slug: string}|null,
     *     courseAdvisor: string
     * }|null
     */
    private function studentProfilePayload(User $user, string $role, string $section): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'profile' || $studentProfile === null) {
            return null;
        }

        $name = $this->splitName($user->name);
        $department = $studentProfile->department;
        $currentLevel = $studentProfile->currentLevel;

        return [
            'name' => [
                'full' => $user->name,
                'surname' => $name['surname'],
                'otherNames' => $name['otherNames'],
            ],
            'email' => $user->email,
            'avatar' => null,
            'registrationNumber' => $studentProfile->registration_number,
            'cgpa' => '4.32',
            'faculty' => $department?->faculty?->only(['name', 'abbreviation']),
            'department' => $department?->only(['name', 'abbreviation']),
            'currentLevel' => $currentLevel === null ? null : $this->levelPayload($currentLevel),
            'courseAdvisor' => $currentLevel === null
                ? 'Department Course Advisor'
                : $this->courseAdvisorName($department, $currentLevel),
        ];
    }

    /**
     * @return array{surname: string, otherNames: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [
            'surname' => $parts[0] ?? '',
            'otherNames' => $parts[1] ?? '',
        ];
    }

    /**
     * @return array{
     *     cgpa: string,
     *     currentSemester: array{name: string, level: string, session: string},
     *     totalCourseUnits: int,
     *     timetable: array<int, array{code: string, title: string, units: int, day: string, time: string, venue: string}>,
     *     pendingDues: array<int, array{title: string, context: string, amount: string, dueDate: string}>,
     *     resits: array<int, array{code: string, title: string, units: int, originalSemester: string, resitWindow: string, status: string}>
     * }|null
     */
    private function studentHomePayload(User $user, string $role, string $section): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'home' || $studentProfile === null) {
            return null;
        }

        $department = $studentProfile->department;
        $currentLevel = $studentProfile->currentLevel;
        $semester = Semester::query()->where('slug', 'harmattan')->first();
        $session = $department?->faculty?->academicSessions()
            ->where('is_current', true)
            ->value('name') ?? '2025/2026';
        $timetable = $this->studentTimetable($department, $currentLevel, $semester);

        return [
            'cgpa' => '4.32',
            'currentSemester' => [
                'name' => $semester?->name ?? 'Harmattan',
                'level' => $currentLevel?->name ?? 'Current Level',
                'session' => $session,
            ],
            'totalCourseUnits' => (int) collect($timetable)->sum('units'),
            'timetable' => $timetable,
            'pendingDues' => $this->studentPendingDues($currentLevel),
            'resits' => $this->studentResits($currentLevel),
        ];
    }

    /**
     * @return array{
     *     pendingAmount: string,
     *     paidAmount: string,
     *     totalDues: int,
     *     pending: array<int, array{name: string, description: string, amount: string, paymentUrl: string}>,
     *     paid: array<int, array{name: string, description: string, amount: string, paidAt: string, receiptUrl: string}>
     * }|null
     */
    private function studentDuesPayload(User $user, string $role, string $section): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'dues' || $studentProfile === null) {
            return null;
        }

        $pending = collect($this->pendingDueRecords($studentProfile->currentLevel))
            ->map(fn (array $due): array => [
                'name' => $due['name'],
                'description' => $due['description'],
                'amount' => $due['amount'],
                'paymentUrl' => $due['paymentUrl'],
            ])
            ->all();
        $paid = [
            [
                'name' => 'Faculty development levy',
                'description' => 'Faculty levy.',
                'amount' => 'NGN 8,000',
                'paidAt' => 'May 4, 2026',
                'receiptUrl' => '#',
            ],
            [
                'name' => 'Library access fee',
                'description' => 'Library access.',
                'amount' => 'NGN 3,000',
                'paidAt' => 'April 18, 2026',
                'receiptUrl' => '#',
            ],
        ];

        return [
            'pendingAmount' => 'NGN 20,000',
            'paidAmount' => 'NGN 11,000',
            'totalDues' => count($pending) + count($paid),
            'pending' => $pending,
            'paid' => $paid,
        ];
    }

    /**
     * @return array{
     *     level: array{id: int, title: string, slug: string},
     *     averageGpa: string,
     *     totalUnits: int,
     *     failedCourses: int,
     *     totalCourses: int,
     *     semesters: array<int, array{name: string, slug: string, gpa: string, totalUnits: int, courses: array<int, array{code: string, title: string, units: int, score: int, grade: string}>}>
     * }|null
     */
    private function studentResultsPayload(User $user, string $role, string $section, ?Level $level): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'results' || $level === null || $studentProfile === null) {
            return null;
        }

        $department = $studentProfile->department;
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();
        $semesterResults = $semesters
            ->map(fn (Semester $semester): array => $this->studentResultSemester($department, $level, $semester))
            ->values()
            ->all();
        $courses = collect($semesterResults)->flatMap(fn (array $semester): array => $semester['courses']);

        return [
            'level' => $this->levelPayload($level),
            'averageGpa' => $this->gpaFor($courses->all()),
            'totalUnits' => (int) $courses->sum('units'),
            'failedCourses' => $courses->where('grade', 'F')->count(),
            'totalCourses' => $courses->count(),
            'semesters' => $semesterResults,
        ];
    }

    /**
     * @return array{
     *     currentSemester: array{name: string, session: string},
     *     maximumCourseUnits: int,
     *     advisorName: string,
     *     reviewStatus: string,
     *     selectedUnits: int,
     *     semesters: array<int, array{id: string, name: string, level: string, session: string, isEditable: bool, totalUnits: int, courses: array<int, array{code: string, title: string, units: int, type: string, status: string}>}>,
     *     validCourses: array<int, array{code: string, title: string, units: int, type: string}>
     * }|null
     */
    private function studentCourseSchedulesPayload(User $user, string $role, string $section): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'course-schedules' || $studentProfile === null) {
            return null;
        }

        $department = $studentProfile->department;
        $currentLevel = $studentProfile->currentLevel;
        $currentSemester = Semester::query()->where('slug', 'harmattan')->first();

        if ($currentLevel === null || $currentSemester === null) {
            return null;
        }

        $currentSession = $department?->faculty?->academicSessions()
            ->where('is_current', true)
            ->value('name') ?? '2025/2026';
        $currentCourses = $this->studentScheduleCourses($department, $currentLevel, $currentSemester, 'Selected');
        $selectedCourses = collect($currentCourses)->take(max(1, count($currentCourses) - 1))->values()->all();
        $editableSemester = [
            'id' => $currentLevel->slug.'-'.$currentSemester->slug.'-'.$currentSession,
            'name' => $currentSemester->name.' Semester',
            'level' => $currentLevel->name,
            'session' => $currentSession,
            'isEditable' => true,
            'totalUnits' => (int) collect($selectedCourses)->sum('units'),
            'courses' => $selectedCourses,
        ];
        $historicalSemesters = $this->studentHistoricalCourseScheduleSemesters(
            $department,
            $currentLevel,
            $currentSession,
        );

        return [
            'currentSemester' => [
                'name' => $currentSemester->name,
                'session' => $currentSession,
            ],
            'maximumCourseUnits' => 24,
            'advisorName' => $this->courseAdvisorName($department, $currentLevel),
            'reviewStatus' => 'Draft',
            'selectedUnits' => (int) collect($selectedCourses)->sum('units'),
            'semesters' => [
                $editableSemester,
                ...$historicalSemesters,
            ],
            'validCourses' => $this->studentCourseScheduleOptions($currentCourses, $currentLevel),
        ];
    }

    /**
     * @return array{
     *     level: array{id: int, title: string, slug: string},
     *     semesters: array<int, array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *         totalUnits: int,
     *         mandatoryCount: int,
     *         optionalCount: int,
     *         offerings: array<int, array{
     *             id: int,
     *             courseId: int,
     *             code: string,
     *             title: string,
     *             units: int,
     *             owner: string,
     *             isMandatory: bool,
     *             requirement: string,
     *             prerequisites: array<int, array{id: int, code: string, title: string}>,
     *             timetableSlotsCount: int,
     *             canEdit: bool
     *         }>
     *     }>
     * }|null
     */
    private function staffCourseSchedulesPayload(User $user, string $role, string $section, ?Level $level): ?array
    {
        if (! in_array($role, ['staff', 'hod'], true) || $section !== 'courses' || $level === null) {
            return null;
        }

        $department = $this->departmentFor($user);

        if ($department === null) {
            return null;
        }

        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();

        return [
            'level' => $this->levelPayload($level),
            'semesters' => $semesters
                ->map(fn (Semester $semester): array => $this->staffCourseScheduleSemesterPayload($department, $level, $semester))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     totalUnits: int,
     *     mandatoryCount: int,
     *     optionalCount: int,
     *     offerings: array<int, array{
     *         id: int,
     *         courseId: int,
     *         code: string,
     *         title: string,
     *         units: int,
     *         owner: string,
     *         isMandatory: bool,
     *         requirement: string,
     *         prerequisites: array<int, array{id: int, code: string, title: string}>,
     *         timetableSlotsCount: int,
     *         canEdit: bool
     *     }>
     * }
     */
    private function staffCourseScheduleSemesterPayload(Department $department, Level $level, Semester $semester): array
    {
        $offerings = $this->courseOfferingsForDepartment($department, $level, $semester);

        return [
            'id' => $level->slug.'-'.$semester->slug,
            'name' => $semester->name,
            'slug' => $semester->slug,
            'totalUnits' => (int) $offerings->sum(fn (CourseOffering $offering): int => (int) $offering->course->credit_units),
            'mandatoryCount' => $offerings->where('is_mandatory', true)->count(),
            'optionalCount' => $offerings->where('is_mandatory', false)->count(),
            'offerings' => $offerings
                ->map(fn (CourseOffering $offering): array => $this->staffCourseOfferingPayload($department, $offering))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, CourseOffering>
     */
    private function courseOfferingsForDepartment(Department $department, Level $level, Semester $semester): Collection
    {
        return CourseOffering::query()
            ->with(['course.prerequisites', 'timetableSlots'])
            ->withCount('timetableSlots')
            ->where('level_id', $level->id)
            ->where('semester_id', $semester->id)
            ->where(function ($query) use ($department): void {
                $this->whereOfferingOwner($query, $department);
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();
    }

    /**
     * @return array{
     *     id: int,
     *     courseId: int,
     *     code: string,
     *     title: string,
     *     units: int,
     *     owner: string,
     *     isMandatory: bool,
     *     requirement: string,
     *     prerequisites: array<int, array{id: int, code: string, title: string}>,
     *     timetableSlotsCount: int,
     *     canEdit: bool
     * }
     */
    private function staffCourseOfferingPayload(Department $department, CourseOffering $offering): array
    {
        $isDepartmentOwned = $offering->owner_type === Department::class
            && (int) $offering->owner_id === (int) $department->id;

        return [
            'id' => (int) $offering->id,
            'courseId' => (int) $offering->course_id,
            'code' => $offering->course->code,
            'title' => $offering->course->title,
            'units' => (int) $offering->course->credit_units,
            'owner' => $isDepartmentOwned ? 'Department' : 'Faculty',
            'isMandatory' => (bool) $offering->is_mandatory,
            'requirement' => $offering->is_mandatory ? 'Mandatory' : 'Optional',
            'prerequisites' => $this->coursePrerequisitePayload($offering->course),
            'timetableSlotsCount' => (int) ($offering->timetable_slots_count ?? $offering->timetableSlots->count()),
            'canEdit' => $isDepartmentOwned,
        ];
    }

    /**
     * @return array{
     *     mode: string,
     *     level: array{id: int, title: string, slug: string},
     *     semester: array{id: int, name: string, slug: string},
     *     offering: array{id: int, code: string, title: string, creditUnits: int, isMandatory: bool, prerequisiteCourseIds: array<int, int>}|null,
     *     prerequisiteOptions: array<int, array{id: int, code: string, title: string, units: int, level: string, semester: string}>
     * }
     */
    private function courseOfferingFormPayload(
        Department $department,
        Level $level,
        Semester $semester,
        ?CourseOffering $offering = null,
    ): array {
        $offering?->loadMissing('course.prerequisites');

        return [
            'mode' => $offering === null ? 'create' : 'edit',
            'level' => $this->levelPayload($level),
            'semester' => $this->semesterPayload($semester),
            'offering' => $offering === null ? null : [
                'id' => (int) $offering->id,
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'creditUnits' => (int) $offering->course->credit_units,
                'isMandatory' => (bool) $offering->is_mandatory,
                'prerequisiteCourseIds' => $offering->course->prerequisites
                    ->pluck('id')
                    ->map(fn (int $id): int => $id)
                    ->values()
                    ->all(),
            ],
            'prerequisiteOptions' => $this->prerequisiteOptions($department, $level, $semester),
        ];
    }

    /**
     * @return array<int, array{id: int, code: string, title: string, units: int, level: string, semester: string}>
     */
    private function prerequisiteOptions(Department $department, Level $level, Semester $semester): array
    {
        return CourseOffering::query()
            ->with(['course', 'level', 'semester'])
            ->where(function ($query) use ($department): void {
                $this->whereOfferingOwner($query, $department);
            })
            ->where(function ($query) use ($level, $semester): void {
                $query
                    ->where('level_id', '<', $level->id)
                    ->orWhere(function ($query) use ($level, $semester): void {
                        $query
                            ->where('level_id', $level->id)
                            ->whereHas('semester', fn ($query) => $query->where('sort_order', '<', $semester->sort_order));
                    });
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => sprintf(
                '%02d-%02d-%s',
                $offering->level_id,
                $offering->semester?->sort_order ?? 0,
                $offering->course->code,
            ))
            ->unique('course_id')
            ->map(fn (CourseOffering $offering): array => [
                'id' => (int) $offering->course->id,
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'units' => (int) $offering->course->credit_units,
                'level' => $offering->level?->name ?? 'Previous Level',
                'semester' => $offering->semester?->name ?? 'Previous Semester',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $courseIds
     * @return array<int, int>
     */
    private function validatedPrerequisiteIds(Department $department, Level $level, Semester $semester, array $courseIds): array
    {
        $ids = collect($courseIds)
            ->map(fn ($courseId): int => (int) $courseId)
            ->filter()
            ->unique()
            ->values();
        $eligibleIds = collect($this->prerequisiteOptions($department, $level, $semester))->pluck('id');

        if ($ids->diff($eligibleIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'prerequisite_course_ids' => 'Prerequisites must come from courses in earlier semesters.',
            ]);
        }

        return $ids->all();
    }

    /**
     * @return array{
     *     level: array{id: int, title: string, slug: string},
     *     semester: array{id: int, name: string, slug: string},
     *     offering: array{id: int, code: string, title: string, units: int, requirement: string, prerequisites: array<int, array{id: int, code: string, title: string}>},
     *     weekdays: array<int, string>,
     *     slots: array<int, array{id: int, dayOfWeek: string, startsAt: string, endsAt: string, venue: string}>
     * }
     */
    private function courseTimetablePayload(Level $level, Semester $semester, CourseOffering $offering): array
    {
        $offering->loadMissing('course.prerequisites');
        $slots = $offering->timetableSlots()
            ->get()
            ->sortBy(fn (CourseTimetableSlot $slot): string => $this->slotSortKey($slot))
            ->values();

        return [
            'level' => $this->levelPayload($level),
            'semester' => $this->semesterPayload($semester),
            'offering' => [
                'id' => (int) $offering->id,
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'units' => (int) $offering->course->credit_units,
                'requirement' => $offering->is_mandatory ? 'Mandatory' : 'Optional',
                'prerequisites' => $this->coursePrerequisitePayload($offering->course),
            ],
            'weekdays' => self::WEEKDAYS,
            'slots' => $slots
                ->map(fn (CourseTimetableSlot $slot): array => [
                    'id' => (int) $slot->id,
                    'dayOfWeek' => $slot->day_of_week,
                    'startsAt' => $this->formatSlotTime($slot->starts_at),
                    'endsAt' => $this->formatSlotTime($slot->ends_at),
                    'venue' => $slot->venue,
                ])
                ->all(),
        ];
    }

    private function resolveLevel(string $level): Level
    {
        return Level::query()->where('slug', $level)->firstOrFail();
    }

    private function resolveSemester(string $semester): Semester
    {
        return Semester::query()->where('slug', $semester)->firstOrFail();
    }

    private function authorizedCourseScheduleUser(Request $request, Level $level): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing([
            'staffProfile.department.faculty',
            'staffProfile.headedFaculty',
        ]);

        $role = $this->dashboardRole($user);

        abort_unless(in_array($role, ['staff', 'hod'], true), 404);
        abort_unless(collect($this->courseScheduleLevelsFor($user, $role))->contains('slug', $level->slug), 404);

        return $user;
    }

    private function editableCourseOffering(
        Department $department,
        CourseOffering $offering,
        Level $level,
        Semester $semester,
    ): CourseOffering {
        $offering->loadMissing('course.prerequisites');

        abort_unless(
            (int) $offering->level_id === (int) $level->id
            && (int) $offering->semester_id === (int) $semester->id
            && $offering->owner_type === Department::class
            && (int) $offering->owner_id === (int) $department->id,
            404,
        );

        return $offering;
    }

    private function manageableCourseOffering(
        Department $department,
        CourseOffering $offering,
        Level $level,
        Semester $semester,
    ): CourseOffering {
        $offering->loadMissing('course.prerequisites');

        abort_unless(
            (int) $offering->level_id === (int) $level->id
            && (int) $offering->semester_id === (int) $semester->id
            && $this->offeringBelongsToDepartmentContext($department, $offering),
            404,
        );

        return $offering;
    }

    private function offeringBelongsToDepartmentContext(Department $department, CourseOffering $offering): bool
    {
        if ($offering->owner_type === Department::class && (int) $offering->owner_id === (int) $department->id) {
            return true;
        }

        return $department->faculty !== null
            && $offering->owner_type === Faculty::class
            && (int) $offering->owner_id === (int) $department->faculty->id;
    }

    /**
     * @param  array{day_of_week: string, starts_at: string, ends_at: string, venue: string}  $slot
     */
    private function ensureTimetableSlotDoesNotClash(
        Department $department,
        CourseOffering $offering,
        array $slot,
        ?CourseTimetableSlot $ignoreSlot = null,
    ): void {
        $query = CourseTimetableSlot::query()
            ->with('courseOffering.course')
            ->where('day_of_week', $slot['day_of_week'])
            ->where('venue', $slot['venue'])
            ->where('starts_at', '<', $slot['ends_at'])
            ->where('ends_at', '>', $slot['starts_at'])
            ->whereHas('courseOffering', function ($query) use ($department, $offering): void {
                $query
                    ->where('level_id', $offering->level_id)
                    ->where('semester_id', $offering->semester_id)
                    ->where(function ($query) use ($department): void {
                        $this->whereOfferingOwner($query, $department);
                    });
            });

        if ($ignoreSlot !== null) {
            $query->whereKeyNot($ignoreSlot->id);
        }

        $clashingSlot = $query->first();

        if ($clashingSlot instanceof CourseTimetableSlot) {
            throw ValidationException::withMessages([
                'venue' => sprintf(
                    '%s already uses %s at that time.',
                    $clashingSlot->courseOffering?->course?->code ?? 'Another course',
                    $slot['venue'],
                ),
            ]);
        }
    }

    private function whereOfferingOwner($query, Department $department): void
    {
        $faculty = $department->faculty;

        $query->where(function ($query) use ($department, $faculty): void {
            $query->where(function ($query) use ($department): void {
                $query
                    ->where('owner_type', Department::class)
                    ->where('owner_id', $department->id);
            });

            if ($faculty !== null) {
                $query->orWhere(function ($query) use ($faculty): void {
                    $query
                        ->where('owner_type', Faculty::class)
                        ->where('owner_id', $faculty->id);
                });
            }
        });
    }

    /**
     * @return array<int, array{id: int, code: string, title: string}>
     */
    private function coursePrerequisitePayload(Course $course): array
    {
        return $course->prerequisites
            ->sortBy('code')
            ->map(fn (Course $prerequisite): array => [
                'id' => (int) $prerequisite->id,
                'code' => $prerequisite->code,
                'title' => $prerequisite->title,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function semesterPayload(Semester $semester): array
    {
        return [
            'id' => (int) $semester->id,
            'name' => $semester->name,
            'slug' => $semester->slug,
        ];
    }

    private function slotSortKey(CourseTimetableSlot $slot): string
    {
        $dayIndex = array_search($slot->day_of_week, self::WEEKDAYS, true);

        return sprintf('%02d-%s', $dayIndex === false ? 99 : $dayIndex, $this->formatSlotTime($slot->starts_at));
    }

    private function formatSlotTime(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }

    /**
     * @return array<int, array{id: string, name: string, level: string, session: string, isEditable: bool, totalUnits: int, courses: array<int, array{code: string, title: string, units: int, type: string, status: string}>}>
     */
    private function studentHistoricalCourseScheduleSemesters(
        ?Department $department,
        Level $currentLevel,
        string $currentSession,
    ): array {
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderByDesc('sort_order')
            ->get()
            ->values();

        return Level::query()
            ->where('id', '<', $currentLevel->id)
            ->orderByDesc('id')
            ->get()
            ->flatMap(function (Level $level) use ($department, $semesters, $currentLevel, $currentSession): array {
                return $semesters
                    ->map(function (Semester $semester) use ($department, $level, $currentLevel, $currentSession): array {
                        $courses = $this->studentScheduleCourses($department, $level, $semester, 'Passed');
                        $courses = collect($courses)
                            ->map(function (array $course) use ($level, $semester): array {
                                $grade = $this->gradeForScore($this->scoreFor($level, $semester, $course['code']));

                                return [
                                    ...$course,
                                    'status' => $grade === 'F' ? 'Failed' : 'Passed',
                                ];
                            })
                            ->values()
                            ->all();

                        return [
                            'id' => $level->slug.'-'.$semester->slug,
                            'name' => $semester->name.' Semester',
                            'level' => $level->name,
                            'session' => $this->sessionForLevel($currentSession, (int) $currentLevel->id, (int) $level->id),
                            'isEditable' => false,
                            'totalUnits' => (int) collect($courses)->sum('units'),
                            'courses' => $courses,
                        ];
                    })
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, type: string, status: string}>
     */
    private function studentScheduleCourses(?Department $department, Level $level, Semester $semester, string $status): array
    {
        return collect($this->studentResultCourses($department, $level, $semester))
            ->map(fn (array $course): array => [
                ...$course,
                'type' => $this->courseType($course['code']),
                'status' => $status,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{code: string, title: string, units: int, type: string, status: string}>  $currentCourses
     * @return array<int, array{code: string, title: string, units: int, type: string}>
     */
    private function studentCourseScheduleOptions(array $currentCourses, Level $currentLevel): array
    {
        return collect($currentCourses)
            ->map(fn (array $course): array => [
                'code' => $course['code'],
                'title' => $course['title'],
                'units' => $course['units'],
                'type' => $course['type'],
            ])
            ->merge($this->extraCourseOptionsFor($currentLevel))
            ->unique('code')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, type: string}>
     */
    private function extraCourseOptionsFor(Level $level): array
    {
        return match ($level->slug) {
            '300-level' => [
                ['code' => 'GST 301', 'title' => 'Entrepreneurship Studies I', 'units' => 2, 'type' => 'General'],
                ['code' => 'CPE 307', 'title' => 'Computer Hardware Laboratory', 'units' => 1, 'type' => 'Core'],
                ['code' => 'CPE 204', 'title' => 'Signals and Systems', 'units' => 3, 'type' => 'Carryover'],
            ],
            '400-level' => [
                ['code' => 'CPE 411', 'title' => 'Computer Engineering Design Studio', 'units' => 2, 'type' => 'Core'],
                ['code' => 'CPE 304', 'title' => 'Database Systems', 'units' => 3, 'type' => 'Carryover'],
            ],
            '500-level' => [
                ['code' => 'CPE 511', 'title' => 'Distributed Systems', 'units' => 2, 'type' => 'Elective'],
                ['code' => 'CPE 403', 'title' => 'Computer Networks', 'units' => 3, 'type' => 'Carryover'],
            ],
            default => [
                ['code' => 'GST 103', 'title' => 'Nigerian Peoples and Culture', 'units' => 2, 'type' => 'General'],
            ],
        };
    }

    private function courseType(string $courseCode): string
    {
        if (str_starts_with($courseCode, 'CPE')) {
            return 'Core';
        }

        if (str_starts_with($courseCode, 'GST')) {
            return 'General';
        }

        return 'Faculty';
    }

    private function courseAdvisorName(?Department $department, Level $level): string
    {
        $assignment = $department?->levelStaffAssignments()
            ->with('staffProfile.user')
            ->where('level_id', $level->id)
            ->first();

        return $assignment?->staffProfile?->user?->name ?? 'Department Course Advisor';
    }

    private function sessionForLevel(string $currentSession, int $currentLevelId, int $levelId): string
    {
        $offset = $currentLevelId - $levelId;

        if (preg_match('/^(?<start>\d{4})\/(?<end>\d{4})$/', $currentSession, $matches) !== 1) {
            $startYear = 2025 - $offset;

            return $startYear.'/'.($startYear + 1);
        }

        $startYear = (int) $matches['start'] - $offset;

        return $startYear.'/'.($startYear + 1);
    }

    /**
     * @return array{name: string, slug: string, gpa: string, totalUnits: int, courses: array<int, array{code: string, title: string, units: int, score: int, grade: string}>}
     */
    private function studentResultSemester(?Department $department, Level $level, Semester $semester): array
    {
        $courses = collect($this->studentResultCourses($department, $level, $semester))
            ->map(function (array $course) use ($level, $semester): array {
                $score = $this->scoreFor($level, $semester, $course['code']);

                return [
                    ...$course,
                    'score' => $score,
                    'grade' => $this->gradeForScore($score),
                ];
            })
            ->values()
            ->all();

        return [
            'name' => $semester->name,
            'slug' => $semester->slug,
            'gpa' => $this->gpaFor($courses),
            'totalUnits' => (int) collect($courses)->sum('units'),
            'courses' => $courses,
        ];
    }

    /**
     * @return array<int, array{code: string, title: string, units: int}>
     */
    private function studentResultCourses(?Department $department, Level $level, Semester $semester): array
    {
        if ($department === null) {
            return $this->fallbackResultCourses($level, $semester);
        }

        $faculty = $department->faculty;
        $offerings = CourseOffering::query()
            ->with('course')
            ->where('level_id', $level->id)
            ->where('semester_id', $semester->id)
            ->where(function ($query) use ($department, $faculty): void {
                $query->where(function ($query) use ($department): void {
                    $query
                        ->where('owner_type', Department::class)
                        ->where('owner_id', $department->id);
                });

                if ($faculty !== null) {
                    $query->orWhere(function ($query) use ($faculty): void {
                        $query
                            ->where('owner_type', Faculty::class)
                            ->where('owner_id', $faculty->id);
                    });
                }
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();

        if ($offerings->isEmpty()) {
            return $this->fallbackResultCourses($level, $semester);
        }

        return $offerings
            ->map(fn (CourseOffering $offering): array => [
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'units' => (int) $offering->course->credit_units,
            ])
            ->all();
    }

    /**
     * @return array<int, array{code: string, title: string, units: int}>
     */
    private function fallbackResultCourses(Level $level, Semester $semester): array
    {
        return collect(self::RESULT_COURSES[$level->slug][$semester->slug] ?? [])
            ->map(fn (array $course): array => [
                'code' => $course[0],
                'title' => $course[1],
                'units' => $course[2],
            ])
            ->all();
    }

    private function scoreFor(Level $level, Semester $semester, string $courseCode): int
    {
        return self::RESULT_SCORES[$level->slug][$semester->slug][$courseCode]
            ?? 50 + (abs(crc32($level->slug.$semester->slug.$courseCode)) % 35);
    }

    private function gradeForScore(int $score): string
    {
        return match (true) {
            $score >= 70 => 'A',
            $score >= 60 => 'B',
            $score >= 50 => 'C',
            $score >= 45 => 'D',
            $score >= 40 => 'E',
            default => 'F',
        };
    }

    private function gradePoint(string $grade): int
    {
        return match ($grade) {
            'A' => 5,
            'B' => 4,
            'C' => 3,
            'D' => 2,
            'E' => 1,
            default => 0,
        };
    }

    /**
     * @param  array<int, array{units: int, grade: string}>  $courses
     */
    private function gpaFor(array $courses): string
    {
        $totalUnits = (int) collect($courses)->sum('units');

        if ($totalUnits === 0) {
            return '0.00';
        }

        $weightedPoints = collect($courses)->sum(
            fn (array $course): int => $course['units'] * $this->gradePoint($course['grade']),
        );

        return number_format($weightedPoints / $totalUnits, 2);
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, day: string, time: string, venue: string}>
     */
    private function studentTimetable(?Department $department, ?Level $level, ?Semester $semester): array
    {
        if ($department === null || $level === null || $semester === null) {
            return [];
        }

        $faculty = $department->faculty;
        $offerings = CourseOffering::query()
            ->with('course')
            ->where('level_id', $level->id)
            ->where('semester_id', $semester->id)
            ->where(function ($query) use ($department, $faculty): void {
                $query->where(function ($query) use ($department): void {
                    $query
                        ->where('owner_type', Department::class)
                        ->where('owner_id', $department->id);
                });

                if ($faculty !== null) {
                    $query->orWhere(function ($query) use ($faculty): void {
                        $query
                            ->where('owner_type', Faculty::class)
                            ->where('owner_id', $faculty->id);
                    });
                }
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();

        if ($offerings->isEmpty()) {
            return $this->fallbackTimetable($level);
        }

        return $offerings
            ->map(fn (CourseOffering $offering, int $index): array => $this->timetableRow(
                $offering->course->code,
                $offering->course->title,
                (int) $offering->course->credit_units,
                $index,
            ))
            ->all();
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, day: string, time: string, venue: string}>
     */
    private function fallbackTimetable(Level $level): array
    {
        $courses = match ($level->slug) {
            '100-level' => [
                ['GST 101', 'Use of English I', 2],
                ['MTH 101', 'Engineering Mathematics I', 3],
                ['PHY 101', 'General Physics I', 3],
                ['CHM 101', 'General Chemistry I', 3],
                ['CPE 101', 'Introduction to Computer Engineering', 2],
            ],
            '200-level' => [
                ['MTH 207', 'Engineering Mathematics III', 3],
                ['CPE 201', 'Digital Logic Design', 3],
                ['CPE 203', 'Circuit Theory', 3],
                ['EEE 201', 'Electrical Machines I', 2],
                ['CSC 201', 'Computer Programming I', 3],
            ],
            '300-level' => [
                ['CPE 301', 'Microprocessor Systems', 3],
                ['CPE 303', 'Operating Systems', 3],
                ['CPE 305', 'Data Structures and Algorithms', 3],
                ['EEE 301', 'Electronic Circuit Design', 3],
                ['MTH 305', 'Numerical Methods', 2],
            ],
            '400-level' => [
                ['CPE 401', 'Embedded Systems Design', 3],
                ['CPE 403', 'Computer Networks', 3],
                ['CPE 405', 'Control Systems Engineering', 2],
                ['CPE 407', 'Digital Signal Processing', 3],
                ['CPE 409', 'Engineering Economics', 2],
            ],
            default => [
                ['CPE 501', 'Digital Systems Project', 3],
                ['CPE 503', 'VLSI Design', 3],
                ['CPE 505', 'Engineering Management', 2],
                ['CPE 507', 'Advanced Computer Architecture', 3],
                ['CPE 509', 'Final Year Project Seminar', 2],
            ],
        };

        return collect($courses)
            ->map(fn (array $course, int $index): array => $this->timetableRow(
                $course[0],
                $course[1],
                $course[2],
                $index,
            ))
            ->all();
    }

    /**
     * @return array{code: string, title: string, units: int, day: string, time: string, venue: string}
     */
    private function timetableRow(string $code, string $title, int $units, int $index): array
    {
        $slot = self::TIMETABLE_SLOTS[$index % count(self::TIMETABLE_SLOTS)];

        return [
            'code' => $code,
            'title' => $title,
            'units' => $units,
            'day' => $slot['day'],
            'time' => $slot['time'],
            'venue' => $slot['venue'],
        ];
    }

    /**
     * @return array<int, array{title: string, context: string, amount: string, dueDate: string}>
     */
    private function studentPendingDues(?Level $level): array
    {
        return collect($this->pendingDueRecords($level))
            ->map(fn (array $due): array => [
                'title' => $due['name'],
                'context' => $due['description'],
                'amount' => $due['amount'],
                'dueDate' => $due['dueDate'],
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, description: string, amount: string, dueDate: string, paymentUrl: string}>
     */
    private function pendingDueRecords(?Level $level): array
    {
        $levelName = $level?->name ?? 'Current Level';

        return [
            [
                'name' => "Departmental Dues {$levelName}",
                'description' => 'CPE departmental levy.',
                'amount' => 'NGN 15,000',
                'dueDate' => 'May 30, 2026',
                'paymentUrl' => '#',
            ],
            [
                'name' => "Student Association Levy {$levelName}",
                'description' => 'Student association levy.',
                'amount' => 'NGN 5,000',
                'dueDate' => 'June 7, 2026',
                'paymentUrl' => '#',
            ],
        ];
    }

    /**
     * @return array{
     *     assignedLevels: array<int, array{id: int, title: string, slug: string}>,
     *     totalStudents: int,
     *     students: array<int, array{
     *         id: int,
     *         fullName: string,
     *         email: string,
     *         registrationNumber: string,
     *         avatar: string|null,
     *         currentLevel: array{id: int, title: string, slug: string}|null,
     *         pendingCarryovers: array<int, string>,
     *         levelGpas: array<int, array{level: string, gpa: string}>,
     *         cgpa: string,
     *         profileUrl: string
     *     }>
     * }|null
     */
    private function staffStudentsPayload(User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'students') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if ($staffProfile === null || $department === null) {
            return [
                'assignedLevels' => [],
                'totalStudents' => 0,
                'students' => [],
            ];
        }

        $assignedLevels = $this->assignedLevelsForStaff($staffProfile, $department);
        $students = $this->assignedStudentsForStaff($staffProfile, $department);

        return [
            'assignedLevels' => $assignedLevels
                ->map(fn (Level $level): array => $this->levelPayload($level))
                ->values()
                ->all(),
            'totalStudents' => $students->count(),
            'students' => $students
                ->map(fn (StudentProfile $studentProfile): array => $this->staffStudentPayload($studentProfile, $department))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     fullName: string,
     *     email: string,
     *     registrationNumber: string,
     *     avatar: string|null,
     *     currentLevel: array{id: int, title: string, slug: string}|null,
     *     pendingCarryovers: array<int, string>,
     *     levelGpas: array<int, array{level: string, gpa: string}>,
     *     cgpa: string,
     *     profileUrl: string
     * }
     */
    private function staffStudentPayload(StudentProfile $studentProfile, Department $department): array
    {
        $currentLevel = $studentProfile->currentLevel;
        $levels = $currentLevel === null
            ? collect()
            : Level::query()
                ->where('id', '<=', $currentLevel->id)
                ->orderBy('id')
                ->get();
        $levelResults = $levels
            ->map(fn (Level $level): array => [
                'level' => $this->shortLevelName($level),
                'courses' => $this->studentLevelResultCourses($department, $level),
            ])
            ->values()
            ->all();
        $levelGpas = collect($levelResults)
            ->map(fn (array $levelResult): array => [
                'level' => $levelResult['level'],
                'gpa' => $this->gpaFor($levelResult['courses']),
            ])
            ->all();

        return [
            'id' => (int) $studentProfile->getKey(),
            'fullName' => $studentProfile->user->name,
            'email' => $studentProfile->user->email,
            'registrationNumber' => $studentProfile->registration_number,
            'avatar' => null,
            'currentLevel' => $currentLevel === null ? null : $this->levelPayload($currentLevel),
            'pendingCarryovers' => collect($this->studentResits($currentLevel))
                ->pluck('code')
                ->values()
                ->all(),
            'levelGpas' => $levelGpas,
            'cgpa' => $this->gpaFor(
                collect($levelResults)
                    ->flatMap(fn (array $levelResult): array => $levelResult['courses'])
                    ->values()
                    ->all(),
            ),
            'profileUrl' => '#',
        ];
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, score: int, grade: string}>
     */
    private function studentLevelResultCourses(Department $department, Level $level): array
    {
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();

        return $semesters
            ->flatMap(fn (Semester $semester): array => $this->studentResultSemester($department, $level, $semester)['courses'])
            ->values()
            ->all();
    }

    private function shortLevelName(Level $level): string
    {
        if (preg_match('/^(?<level>\d+)00 Level$/', $level->name, $matches) === 1) {
            return $matches['level'].'00L';
        }

        return str_replace(' Level', 'L', $level->name);
    }

    /**
     * @return array{
     *     assignedLevels: array<int, array{id: int, title: string, slug: string}>,
     *     totalStudents: int,
     *     maximumCourseUnits: int,
     *     studentsWithCarryovers: int,
     *     pendingApprovalsCount: int,
     *     pendingApprovals: array<int, array{studentName: string, registrationNumber: string, requestType: string, submittedAt: string}>,
     *     pendingDuesByStudent: array<int, array{studentName: string, registrationNumber: string, dues: array<int, string>, outstandingAmount: string}>,
     *     recentPayments: array<int, array{studentName: string, dueName: string, amount: string, paidAt: string}>,
     *     carryoverSummaries: array<int, array{code: string, title: string, units: int, students: array<int, string>}>
     * }|null
     */
    private function staffHomePayload(User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'home') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if ($staffProfile === null || $department === null) {
            return $this->emptyStaffHomePayload();
        }

        $assignedLevels = $this->assignedLevelsForStaff($staffProfile, $department);
        $students = $this->assignedStudentsForStaff($staffProfile, $department);
        $carryoverSummaries = $this->staffCarryoverSummaries($students);
        $pendingApprovals = $this->staffPendingApprovals($students);

        return [
            'assignedLevels' => $assignedLevels
                ->map(fn (Level $level): array => $this->levelPayload($level))
                ->values()
                ->all(),
            'totalStudents' => $students->count(),
            'maximumCourseUnits' => 24,
            'studentsWithCarryovers' => $students
                ->filter(fn ($studentProfile): bool => $studentProfile->currentLevel !== null
                    && $this->studentResits($studentProfile->currentLevel) !== [])
                ->count(),
            'pendingApprovalsCount' => count($pendingApprovals),
            'pendingApprovals' => $pendingApprovals,
            'pendingDuesByStudent' => $this->staffPendingDuesByStudent($students),
            'recentPayments' => $this->staffRecentPayments($students),
            'carryoverSummaries' => $carryoverSummaries,
        ];
    }

    /**
     * @return array{
     *     assignedLevels: array<int, array{id: int, title: string, slug: string}>,
     *     totalStudents: int,
     *     maximumCourseUnits: int,
     *     studentsWithCarryovers: int,
     *     pendingApprovalsCount: int,
     *     pendingApprovals: array<int, array{studentName: string, registrationNumber: string, requestType: string, submittedAt: string}>,
     *     pendingDuesByStudent: array<int, array{studentName: string, registrationNumber: string, dues: array<int, string>, outstandingAmount: string}>,
     *     recentPayments: array<int, array{studentName: string, dueName: string, amount: string, paidAt: string}>,
     *     carryoverSummaries: array<int, array{code: string, title: string, units: int, students: array<int, string>}>
     * }
     */
    private function emptyStaffHomePayload(): array
    {
        return [
            'assignedLevels' => [],
            'totalStudents' => 0,
            'maximumCourseUnits' => 24,
            'studentsWithCarryovers' => 0,
            'pendingApprovalsCount' => 0,
            'pendingApprovals' => [],
            'pendingDuesByStudent' => [],
            'recentPayments' => [],
            'carryoverSummaries' => [],
        ];
    }

    private function assignedLevelsForStaff(StaffProfile $staffProfile, Department $department): EloquentCollection
    {
        return $staffProfile->assignedLevels()
            ->wherePivot('department_id', $department->id)
            ->orderBy('levels.id')
            ->get();
    }

    /**
     * @return Collection<int, StudentProfile>
     */
    private function assignedStudentsForStaff(StaffProfile $staffProfile, Department $department): Collection
    {
        $assignedLevels = $this->assignedLevelsForStaff($staffProfile, $department);

        if ($assignedLevels->isEmpty()) {
            return collect();
        }

        return $department->studentProfiles()
            ->with(['currentLevel', 'user'])
            ->whereIn('current_level_id', $assignedLevels->pluck('id'))
            ->get()
            ->sortBy(fn (StudentProfile $studentProfile): string => sprintf(
                '%02d-%s',
                $studentProfile->current_level_id,
                $studentProfile->user->name,
            ))
            ->values();
    }

    private function nairaAmount(string $amount): int
    {
        return (int) preg_replace('/\D+/', '', $amount);
    }

    private function formatNairaAmount(int $amount): string
    {
        return 'NGN '.number_format($amount);
    }

    /**
     * @param  iterable<int, mixed>  $students
     * @return array<int, array{studentName: string, registrationNumber: string, dues: array<int, string>, outstandingAmount: string}>
     */
    private function staffPendingDuesByStudent(iterable $students): array
    {
        return collect($students)
            ->map(function ($studentProfile): array {
                $dues = $this->pendingDueRecords($studentProfile->currentLevel);
                $outstandingValue = collect($dues)
                    ->sum(fn (array $due): int => $this->nairaAmount($due['amount']));

                return [
                    'studentName' => $studentProfile->user->name,
                    'registrationNumber' => $studentProfile->registration_number,
                    'dues' => collect($dues)
                        ->pluck('name')
                        ->values()
                        ->all(),
                    'outstandingAmount' => $this->formatNairaAmount($outstandingValue),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $students
     * @return array<int, array{studentName: string, dueName: string, amount: string, paidAt: string}>
     */
    private function staffRecentPayments(iterable $students): array
    {
        $payments = [
            ['dueName' => 'Faculty development levy', 'amount' => 'NGN 8,000', 'paidAt' => 'May 4, 2026'],
            ['dueName' => 'Library access fee', 'amount' => 'NGN 3,000', 'paidAt' => 'April 28, 2026'],
            ['dueName' => 'Student Association Levy', 'amount' => 'NGN 5,000', 'paidAt' => 'April 20, 2026'],
        ];

        return collect($students)
            ->take(3)
            ->values()
            ->map(fn ($studentProfile, int $index): array => [
                'studentName' => $studentProfile->user->name,
                ...$payments[$index % count($payments)],
            ])
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $students
     * @return array<int, array{studentName: string, registrationNumber: string, requestType: string, submittedAt: string}>
     */
    private function staffPendingApprovals(iterable $students): array
    {
        $requests = [
            ['requestType' => 'Course schedule review', 'submittedAt' => 'May 12, 2026'],
            ['requestType' => 'Profile change', 'submittedAt' => 'May 11, 2026'],
            ['requestType' => 'Carryover registration', 'submittedAt' => 'May 9, 2026'],
            ['requestType' => 'Course schedule review', 'submittedAt' => 'May 7, 2026'],
        ];

        return collect($students)
            ->take(4)
            ->values()
            ->map(fn ($studentProfile, int $index): array => [
                'studentName' => $studentProfile->user->name,
                'registrationNumber' => $studentProfile->registration_number,
                ...$requests[$index % count($requests)],
            ])
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $students
     * @return array<int, array{code: string, title: string, units: int, students: array<int, string>}>
     */
    private function staffCarryoverSummaries(iterable $students): array
    {
        $summaries = [];

        foreach ($students as $studentProfile) {
            foreach ($this->studentResits($studentProfile->currentLevel) as $resit) {
                $code = $resit['code'];

                $summaries[$code] ??= [
                    'code' => $code,
                    'title' => $resit['title'],
                    'units' => $resit['units'],
                    'students' => [],
                ];

                $summaries[$code]['students'][] = $studentProfile->user->name;
            }
        }

        ksort($summaries);

        return array_values($summaries);
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, originalSemester: string, resitWindow: string, status: string}>
     */
    private function studentResits(?Level $level): array
    {
        if ($level === null || (int) $level->id <= 1) {
            return [];
        }

        $resits = [
            [
                'code' => 'MTH 201',
                'title' => 'Engineering Mathematics III',
                'units' => 3,
                'originalSemester' => '200 Level, Harmattan',
                'resitWindow' => 'Rain 2025/2026',
                'status' => 'Advisor review',
            ],
        ];

        if ((int) $level->id >= 4) {
            $resits[] = [
                'code' => 'CPE 302',
                'title' => 'Electronic Circuit Design',
                'units' => 3,
                'originalSemester' => '300 Level, Rain',
                'resitWindow' => 'Harmattan 2026/2027',
                'status' => 'Due next session',
            ];
        }

        return $resits;
    }

    /**
     * @return array{id: int, title: string, slug: string}
     */
    private function levelPayload(Level $level): array
    {
        return [
            'id' => $level->id,
            'title' => $level->name,
            'slug' => $level->slug,
        ];
    }
}
