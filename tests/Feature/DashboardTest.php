<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Level;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('student users see the student dashboard shell', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $currentLevel = Level::query()->where('slug', '400-level')->firstOrFail();
    $user = User::factory()->student()->create();

    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_level_id' => $currentLevel->id,
        'registration_number' => '2021009999',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'student')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'Student')
        ->where('dashboard.student.registrationNumber', '2021009999')
        ->where('dashboard.student.currentLevel.title', '400 Level')
        ->has('dashboard.levels', 4)
        ->where('dashboard.levels.0.slug', '100-level')
        ->where('dashboard.levels.3.slug', '400-level')
        ->where('dashboard.studentHome.cgpa', '4.32')
        ->where('dashboard.studentHome.currentSemester.name', 'Harmattan')
        ->where('dashboard.studentHome.currentSemester.session', '2025/2026')
        ->where('dashboard.studentHome.totalCourseUnits', 13)
        ->has('dashboard.studentHome.timetable', 5)
        ->has('dashboard.studentHome.pendingDues', 2)
        ->where('dashboard.studentHome.pendingDues.0.title', 'Departmental Dues 400 Level')
        ->where('dashboard.studentHome.pendingDues.0.amount', 'NGN 15,000')
        ->has('dashboard.studentHome.resits', 2)
        ->where('dashboard.staffHome', null)
        ->where('dashboard.studentResults', null)
        ->where('dashboard.studentDues', null)
        ->etc());
});

test('student users see pending and paid dues records', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $currentLevel = Level::query()->where('slug', '300-level')->firstOrFail();
    $user = User::factory()->student()->create();

    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_level_id' => $currentLevel->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'dues',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'student')
        ->where('dashboard.section', 'dues')
        ->where('dashboard.studentHome', null)
        ->where('dashboard.studentResults', null)
        ->has('dashboard.studentDues', fn (Assert $page) => $page
            ->where('pendingAmount', 'NGN 20,000')
            ->where('paidAmount', 'NGN 11,000')
            ->where('totalDues', 4)
            ->has('pending', 2)
            ->where('pending.0.name', 'Departmental Dues 300 Level')
            ->where('pending.0.amount', 'NGN 15,000')
            ->where('pending.0.paymentUrl', '#')
            ->has('paid', 2)
            ->where('paid.0.name', 'Faculty development levy')
            ->where('paid.0.amount', 'NGN 8,000')
            ->where('paid.0.paidAt', 'May 4, 2026')
            ->where('paid.0.receiptUrl', '#')
        )
        ->etc());
});

test('student result levels stop at their current academic level', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $currentLevel = Level::query()->where('slug', '300-level')->firstOrFail();
    $user = User::factory()->student()->create();

    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_level_id' => $currentLevel->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.level', [
        'section' => 'results',
        'level' => '300-level',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'student')
        ->where('dashboard.section', 'results')
        ->where('dashboard.studentHome', null)
        ->where('dashboard.studentDues', null)
        ->has('dashboard.studentResults', fn (Assert $page) => $page
            ->where('level.title', '300 Level')
            ->where('averageGpa', '3.61')
            ->where('totalUnits', 28)
            ->where('failedCourses', 1)
            ->where('totalCourses', 10)
            ->has('semesters', 2)
            ->where('semesters.0.name', 'Harmattan')
            ->where('semesters.0.gpa', '4.14')
            ->where('semesters.0.totalUnits', 14)
            ->has('semesters.0.courses', 5)
            ->where('semesters.0.courses.0.code', 'CPE 301')
            ->where('semesters.1.name', 'Rain')
            ->where('semesters.1.gpa', '3.07')
            ->where('semesters.1.totalUnits', 14)
            ->has('semesters.1.courses', 5)
            ->where('semesters.1.courses.1.code', 'CPE 304')
            ->where('semesters.1.courses.1.score', 38)
            ->where('semesters.1.courses.1.grade', 'F')
        )
        ->etc());

    $this->actingAs($user)->get(route('dashboard.level', [
        'section' => 'results',
        'level' => '400-level',
    ]))->assertNotFound();
});

test('faculty heads see the faculty head dashboard shell', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $user = User::factory()->staff()->create();
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($staffProfile);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'faculty_head')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'Faculty Head')
        ->where('dashboard.staffHome', null)
        ->etc());
});

test('staff users see the staff dashboard shell', function () {
    $user = User::factory()->staff()->create();

    StaffProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'Staff')
        ->where('dashboard.staffHome.totalStudents', 0)
        ->has('dashboard.staffHome.assignedLevels', 0)
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('staff home is scoped to assigned student sets', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $assignedLevel = Level::query()->where('slug', '300-level')->firstOrFail();
    $otherLevel = Level::query()->where('slug', '400-level')->firstOrFail();
    $user = User::factory()->staff()->create();
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignStaffToLevel($assignedLevel, $staffProfile);

    foreach ([
        ['name' => 'Ada Nwosu', 'registration_number' => '2023001001'],
        ['name' => 'Chidera Eze', 'registration_number' => '2023001002'],
    ] as $student) {
        $studentUser = User::factory()->student()->create([
            'name' => $student['name'],
        ]);

        StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'current_level_id' => $assignedLevel->id,
            'registration_number' => $student['registration_number'],
        ]);
    }

    $outsideUser = User::factory()->student()->create([
        'name' => 'Ifeoma Obi',
    ]);

    StudentProfile::factory()->create([
        'user_id' => $outsideUser->id,
        'department_id' => $department->id,
        'current_level_id' => $otherLevel->id,
        'registration_number' => '2022001003',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'home')
        ->has('dashboard.staffHome', fn (Assert $page) => $page
            ->has('assignedLevels', 1)
            ->where('assignedLevels.0.title', '300 Level')
            ->where('totalStudents', 2)
            ->where('maximumCourseUnits', 24)
            ->where('studentsWithCarryovers', 2)
            ->where('pendingApprovalsCount', 2)
            ->has('pendingApprovals', 2)
            ->where('pendingApprovals.0.studentName', 'Ada Nwosu')
            ->has('pendingDuesByStudent', 2)
            ->where('pendingDuesByStudent.0.studentName', 'Ada Nwosu')
            ->where('pendingDuesByStudent.0.registrationNumber', '2023001001')
            ->has('pendingDuesByStudent.0.dues', 2)
            ->where('pendingDuesByStudent.0.dues.0', 'Departmental Dues 300 Level')
            ->where('pendingDuesByStudent.0.outstandingAmount', 'NGN 20,000')
            ->has('recentPayments', 2)
            ->where('recentPayments.0.studentName', 'Ada Nwosu')
            ->has('carryoverSummaries', 1)
            ->where('carryoverSummaries.0.code', 'MTH 201')
            ->has('carryoverSummaries.0.students', 2)
        )
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('staff students page lists assigned students alphabetically', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $assignedLevel = Level::query()->where('slug', '300-level')->firstOrFail();
    $otherLevel = Level::query()->where('slug', '400-level')->firstOrFail();
    $user = User::factory()->staff()->create();
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignStaffToLevel($assignedLevel, $staffProfile);

    foreach ([
        ['name' => 'Chidera Eze', 'email' => 'chidera.eze@example.com', 'registration_number' => '2023001002'],
        ['name' => 'Ada Nwosu', 'email' => 'ada.nwosu@example.com', 'registration_number' => '2023001001'],
    ] as $student) {
        $studentUser = User::factory()->student()->create([
            'name' => $student['name'],
            'email' => $student['email'],
        ]);

        StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'current_level_id' => $assignedLevel->id,
            'registration_number' => $student['registration_number'],
        ]);
    }

    $outsideUser = User::factory()->student()->create([
        'name' => 'Ifeoma Obi',
    ]);

    StudentProfile::factory()->create([
        'user_id' => $outsideUser->id,
        'department_id' => $department->id,
        'current_level_id' => $otherLevel->id,
        'registration_number' => '2022001003',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'students',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'students')
        ->where('dashboard.staffHome', null)
        ->has('dashboard.staffStudents', fn (Assert $page) => $page
            ->has('assignedLevels', 1)
            ->where('assignedLevels.0.title', '300 Level')
            ->where('totalStudents', 2)
            ->has('students', 2)
            ->where('students.0.fullName', 'Ada Nwosu')
            ->where('students.0.email', 'ada.nwosu@example.com')
            ->where('students.0.registrationNumber', '2023001001')
            ->where('students.0.currentLevel.title', '300 Level')
            ->where('students.0.profileUrl', '#')
            ->has('students.0.pendingCarryovers', 1)
            ->where('students.0.pendingCarryovers.0', 'MTH 201')
            ->has('students.0.levelGpas', 3)
            ->where('students.0.levelGpas.0.level', '100L')
            ->where('students.0.cgpa', '3.73')
            ->where('students.1.fullName', 'Chidera Eze')
        )
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('department heads see the hod dashboard shell', function () {
    $department = Department::factory()->create();
    $user = User::factory()->staff()->create();
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($staffProfile);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'hod')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'Head of Department')
        ->where('dashboard.staffHome', null)
        ->etc());
});

test('dashboard section routes render placeholders for allowed roles', function () {
    $this->seed(AcademicStructureSeeder::class);

    $user = User::factory()->staff()->create();

    StaffProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.level', [
        'section' => 'courses',
        'level' => '100-level',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'courses')
        ->where('dashboard.level', '100 Level')
        ->etc());

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'approvals',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'staff')
            ->where('dashboard.section', 'approvals')
            ->where('dashboard.sectionTitle', 'Approvals')
            ->where('dashboard.staffHome', null)
            ->etc());
});

test('dashboard routes reject sections outside the current role', function () {
    $user = User::factory()->student()->create();

    StudentProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'students',
    ]));

    $response->assertNotFound();
});

test('users can logout from the dashboard', function () {
    $user = User::factory()->student()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

test('departments replace the current hod when a new staff profile is assigned', function () {
    $department = Department::factory()->create();
    $firstProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);
    $secondProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($firstProfile);

    expect($department->hod_staff_profile_id)->toBe($firstProfile->id);

    $department->assignHeadOfDepartment($secondProfile);

    expect($department->hod_staff_profile_id)->toBe($secondProfile->id);
});
