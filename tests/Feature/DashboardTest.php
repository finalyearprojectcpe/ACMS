<?php

use App\AccountType;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\EmailDelivery;
use App\Models\Faculty;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('student users see the student dashboard shell', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $currentLevel = AcademicYear::query()->where('slug', '400-level')->firstOrFail();
    $user = User::factory()->student()->create();

    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
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
        ->has('dashboard.studentHome.courses', 5)
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
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $user = User::factory()->student()->create();

    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
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
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $user = User::factory()->student()->create();

    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
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
        ->where('dashboard.facultyHead.metrics.departments', 1)
        ->where('dashboard.facultyHead.metrics.hodCoverage', '0/1')
        ->where('dashboard.facultyHead.departments.0.status', 'Vacant')
        ->where('dashboard.facultyHead.pendingTasks.0.status', 'Needs action')
        ->where('dashboard.staffHome', null)
        ->etc());

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'approvals',
    ]))->assertNotFound();
});

test('school admins see the school admin dashboard shell', function () {
    $school = School::factory()->create([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
    ]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'name' => 'Electrical Systems and Engineering Technology',
        'abbreviation' => 'SESET',
    ]);
    Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
        'name' => 'Okoro Chinyere',
        'email' => 'schooladmin@futo.edu',
    ]);

    $response = $this->actingAs($schoolAdmin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'school_admin')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'School Admin')
        ->where('dashboard.schoolAdmin.school.abbreviation', 'FUTO')
        ->where('dashboard.schoolAdmin.metrics.faculties', 1)
        ->where('dashboard.schoolAdmin.metrics.departments', 1)
        ->where('dashboard.schoolAdmin.metrics.academicSessions', 1)
        ->where('dashboard.schoolAdmin.currentAcademicSession.name', '2025/2026')
        ->where('dashboard.schoolAdmin.faculties.0.abbreviation', 'SESET')
        ->where('dashboard.facultyHead', null)
        ->where('dashboard.staffHome', null)
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('school admins manage school academic sessions', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
    ]);

    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);

    $created = $this->actingAs($schoolAdmin)->post(route('dashboard.academic-sessions.store'), [
        'name' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
    ]);

    $created->assertRedirect(route('dashboard.section', [
        'section' => 'academic-sessions',
        'academic_session' => AcademicSession::query()->where('name', '2026/2027')->firstOrFail()->id,
    ]));
    $created->assertSessionHas('success', 'Academic session saved.');

    $overlapping = $this->actingAs($schoolAdmin)->post(route('dashboard.academic-sessions.store'), [
        'name' => 'Overlap',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
    ]);

    $overlapping->assertSessionHasErrors('starts_on');
});

test('school admins create faculties and departments', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
    ]);

    $facultyResponse = $this->actingAs($schoolAdmin)->post(route('dashboard.structure.faculties.store'), [
        'name' => 'Electrical Systems and Engineering Technology',
        'abbreviation' => 'SESET',
    ]);

    $facultyResponse->assertRedirect(route('dashboard.section', [
        'section' => 'structure',
    ]));

    $faculty = Faculty::query()
        ->where('school_id', $school->id)
        ->where('abbreviation', 'SESET')
        ->firstOrFail();

    $departmentResponse = $this->actingAs($schoolAdmin)->post(route('dashboard.structure.departments.store'), [
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
        'minimum_years_to_degree' => 5,
    ]);

    $departmentResponse->assertRedirect(route('dashboard.section', [
        'section' => 'structure',
    ]));

    expect($faculty->departments()->where('abbreviation', 'CPE')->firstOrFail()->minimum_years_to_degree)->toBe(5);
});

test('school admins provision faculty heads', function () {
    $school = School::factory()->create([
        'abbreviation' => 'FUTO',
    ]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'abbreviation' => 'SESET',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'CPE',
    ]);
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
    ]);

    $response = $this->actingAs($schoolAdmin)->post(route('dashboard.faculty-heads.store'), [
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'name' => 'Nwankwo Chika',
        'email' => 'facultyhead.seset@example.com',
        'access_code' => 'FUTO-SESET-HEAD',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'faculty-heads',
    ]));
    $response->assertSessionHas('success', 'Faculty head saved.');

    $facultyHead = User::query()
        ->where('school_id', $school->id)
        ->where('email', 'facultyhead.seset@example.com')
        ->firstOrFail();

    expect($facultyHead->account_type)->toBe(AccountType::Staff)
        ->and($facultyHead->access_code)->toBe('FUTO-SESET-HEAD')
        ->and($facultyHead->activated_at)->toBeNull()
        ->and($facultyHead->staffProfile->department_id)->toBe($department->id)
        ->and($faculty->refresh()->head_staff_profile_id)->toBe($facultyHead->staffProfile->id)
        ->and(EmailDelivery::query()->where('user_id', $facultyHead->id)->where('status', 'skipped')->exists())->toBeTrue();
});

test('faculty heads can assign department staff as department heads with visible one time access codes', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $hodUser = User::factory()->staff()->pendingActivation('SESET-CPE-123456')->create([
        'school_id' => $faculty->school_id,
        'name' => 'Okafor Chinedu',
        'email' => 'hod.cpe@example.com',
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $department->id,
        'staff_profile_id' => $hodProfile->id,
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'departments',
    ]));

    $hodUser->refresh();

    expect($hodUser->account_type)->toBe(AccountType::Staff)
        ->and($hodUser->name)->toBe('Okafor Chinedu')
        ->and($hodUser->access_code)->toBe('SESET-CPE-123456')
        ->and($hodUser->access_code_used_at)->toBeNull()
        ->and($hodUser->activated_at)->toBeNull()
        ->and($department->refresh()->hod_staff_profile_id)->toBe($hodProfile->id);

    expect(EmailDelivery::query()
        ->where('user_id', $hodUser->id)
        ->where('recipient_email', 'hod.cpe@example.com')
        ->where('status', 'skipped')
        ->exists())->toBeTrue();
});

test('faculty heads can assign a staff row as hod of their department', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $staffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Nneka Okorie',
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $department->id,
        'staff_profile_id' => $staffProfile->id,
        'redirect_section' => 'staff',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));

    expect($department->refresh()->hod_staff_profile_id)->toBe($staffProfile->id);
});

test('faculty heads can only see faculty staff from the staff page', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'CPE',
    ]);
    $otherFaculty = Faculty::factory()->create([
        'school_id' => $faculty->school_id,
        'abbreviation' => 'SAAT',
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $otherFaculty->id,
        'abbreviation' => 'AGR',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Ada Eze',
        'email' => 'ada.eze@example.com',
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $facultyStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Nneka Okorie',
        'email' => 'nneka.okorie@example.com',
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Ikenna Ude',
        'email' => 'ikenna.ude@example.com',
    ]);
    $otherStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Chinedu Okafor',
        'email' => 'chinedu.okafor@example.com',
    ]);

    StaffProfile::factory()->create([
        'user_id' => $facultyStaffUser->id,
        'department_id' => $department->id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    StaffProfile::factory()->create([
        'user_id' => $otherStaffUser->id,
        'department_id' => $otherDepartment->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);
    $department->assignHeadOfDepartment($hodProfile);

    $response = $this->actingAs($facultyHeadUser)->get(route('dashboard.section', [
        'section' => 'staff',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'faculty_head')
        ->where('dashboard.section', 'staff')
        ->has('dashboard.facultyHead.staff', 3)
        ->where('dashboard.facultyHead.staff.0.email', 'ada.eze@example.com')
        ->where('dashboard.facultyHead.staff.0.role', 'Faculty Head')
        ->where('dashboard.facultyHead.staff.0.roleContext', $faculty->abbreviation)
        ->where('dashboard.facultyHead.staff.0.canAssignHod', false)
        ->where('dashboard.facultyHead.staff.0.assignmentBlockReason', 'Already Faculty Head')
        ->where('dashboard.facultyHead.staff.1.email', 'ikenna.ude@example.com')
        ->where('dashboard.facultyHead.staff.1.role', 'HOD')
        ->where('dashboard.facultyHead.staff.1.roleContext', 'CPE')
        ->where('dashboard.facultyHead.staff.1.department.id', $department->id)
        ->where('dashboard.facultyHead.staff.1.department.abbreviation', 'CPE')
        ->where('dashboard.facultyHead.staff.1.canAssignHod', false)
        ->where('dashboard.facultyHead.staff.1.assignmentBlockReason', 'Already HOD of CPE')
        ->where('dashboard.facultyHead.staff.2.email', 'nneka.okorie@example.com')
        ->where('dashboard.facultyHead.staff.2.department.id', $department->id)
        ->where('dashboard.facultyHead.staff.2.department.abbreviation', 'CPE')
        ->where('dashboard.facultyHead.staff.2.faculty.abbreviation', $faculty->abbreviation)
        ->where('dashboard.facultyHead.staff.2.roleContext', 'CPE')
        ->where('dashboard.facultyHead.staff.2.canAssignHod', true)
        ->where('dashboard.facultyHead.staff.2.assignmentBlockReason', null)
        ->etc());

    $formResponse = $this->actingAs($facultyHeadUser)->get(route('dashboard.departments.hod', [
        'department' => $department,
    ]));

    $formResponse->assertOk();
    $formResponse->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.section', 'departments')
        ->where('dashboard.facultyHeadHodForm.department.abbreviation', 'CPE')
        ->where('dashboard.facultyHeadHodForm.department.currentHod.name', 'Ikenna Ude')
        ->has('dashboard.facultyHeadHodForm.staff', 3)
        ->where('dashboard.facultyHeadHodForm.staff.1.email', 'ikenna.ude@example.com')
        ->where('dashboard.facultyHeadHodForm.staff.1.canAssignHod', false)
        ->where('dashboard.facultyHeadHodForm.staff.1.assignmentBlockReason', 'Already HOD of CPE')
        ->etc());
});

test('faculty heads cannot assign a hod outside their faculty', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $otherFaculty = Faculty::factory()->create([
        'school_id' => $faculty->school_id,
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $otherFaculty->id,
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $staffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $otherDepartment->id,
        'staff_profile_id' => $staffProfile->id,
    ])->assertNotFound();
});

test('faculty heads cannot assign outside faculty staff as department heads', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $otherFaculty = Faculty::factory()->create([
        'school_id' => $faculty->school_id,
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $otherFaculty->id,
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $outsideStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $outsideStaffProfile = StaffProfile::factory()->create([
        'user_id' => $outsideStaffUser->id,
        'department_id' => $otherDepartment->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $department->id,
        'staff_profile_id' => $outsideStaffProfile->id,
    ])->assertNotFound();
});

test('faculty heads cannot assign staff from another department as department hod', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $otherDepartmentStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $otherDepartmentStaffProfile = StaffProfile::factory()->create([
        'user_id' => $otherDepartmentStaffUser->id,
        'department_id' => $otherDepartment->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $department->id,
        'staff_profile_id' => $otherDepartmentStaffProfile->id,
    ])->assertNotFound();
});

test('faculty heads can create faculty staff access codes', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.store'), [
        'department_id' => $department->id,
        'name' => 'Ifeoma Nwosu',
        'email' => 'ifeoma.nwosu@example.com',
        'access_code' => 'SESET-CPE-STAFF',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));

    $staffUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'ifeoma.nwosu@example.com')
        ->firstOrFail();

    expect($staffUser->account_type)->toBe(AccountType::Staff)
        ->and($staffUser->name)->toBe('Ifeoma Nwosu')
        ->and($staffUser->access_code)->toBe('SESET-CPE-STAFF')
        ->and($staffUser->access_code_used_at)->toBeNull()
        ->and($staffUser->activated_at)->toBeNull()
        ->and($staffUser->staffProfile->department_id)->toBe($department->id)
        ->and(EmailDelivery::query()
            ->where('user_id', $staffUser->id)
            ->where('recipient_email', 'ifeoma.nwosu@example.com')
            ->where('status', 'skipped')
            ->exists())->toBeTrue();
});

test('faculty heads can import faculty staff from spreadsheet rows', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.import.store'), [
        'file_name' => 'staff.xlsx',
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Chika Obi',
                'email' => 'chika.obi@example.com',
                'accessCode' => 'SESET-CPE-BULK',
            ],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));
    $response->assertSessionHas('success', 'Imported 1 staff record from spreadsheet.');

    $staffUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'chika.obi@example.com')
        ->firstOrFail();

    expect($staffUser->account_type)->toBe(AccountType::Staff)
        ->and($staffUser->access_code)->toBe('SESET-CPE-BULK')
        ->and($staffUser->staffProfile->department_id)->toBe($department->id)
        ->and(ImportBatch::query()->where('file_name', 'staff.xlsx')->where('type', 'staff_import')->exists())->toBeTrue()
        ->and(EmailDelivery::query()->where('user_id', $staffUser->id)->where('status', 'skipped')->exists())->toBeTrue();
});

test('faculty heads can import faculty staff in frontend batches', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $firstBatch = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.import.store'), [
        'file_name' => 'staff.xlsx',
        'import_key' => 'faculty-staff-import-1',
        'batch_number' => 1,
        'total_batches' => 2,
        'start_row' => 1,
        'total_rows' => 2,
        'batch_size' => 1,
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Chika Obi',
                'email' => 'chika.obi@example.com',
                'accessCode' => 'SESET-CPE-BULK-1',
            ],
        ],
    ]);

    $firstBatch->assertRedirect(route('dashboard.staff.import'));
    $firstBatch->assertSessionHas('success', 'Imported batch 1 of 2. Continue with the next batch.');

    $secondBatch = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.import.store'), [
        'file_name' => 'staff.xlsx',
        'import_key' => 'faculty-staff-import-1',
        'batch_number' => 2,
        'total_batches' => 2,
        'start_row' => 2,
        'total_rows' => 2,
        'batch_size' => 1,
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Ada Umeh',
                'email' => 'ada.umeh@example.com',
                'accessCode' => 'SESET-CPE-BULK-2',
            ],
        ],
    ]);

    $secondBatch->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));
    $secondBatch->assertSessionHas('success', 'Imported 2 staff records from spreadsheet.');

    $importBatch = ImportBatch::query()
        ->where('import_key', 'faculty-staff-import-1')
        ->where('type', 'staff_import')
        ->firstOrFail();

    expect($importBatch->summary)->toMatchArray([
        'submitted_rows' => 2,
        'total_batches' => 2,
        'processed_batches' => 2,
        'imported_staff' => 2,
    ])
        ->and($importBatch->rows()->orderBy('row_number')->pluck('row_number')->all())->toBe([1, 2])
        ->and(User::query()->where('school_id', $faculty->school_id)->whereIn('email', [
            'chika.obi@example.com',
            'ada.umeh@example.com',
        ])->count())->toBe(2);
});

test('faculty staff spreadsheet imports generate missing access codes', function () {
    $faculty = Faculty::factory()->create([
        'abbreviation' => 'SESET',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.import.store'), [
        'file_name' => 'staff.xlsx',
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Obinna Eze',
                'email' => 'obinna.eze@example.com',
                'accessCode' => '',
            ],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));

    $staffUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'obinna.eze@example.com')
        ->firstOrFail();

    expect($staffUser->access_code)->toStartWith('SESET-CPE-')
        ->and($staffUser->access_code)->not->toBe('SESET-CPE-')
        ->and($staffUser->access_code_used_at)->toBeNull()
        ->and($staffUser->activated_at)->toBeNull()
        ->and(EmailDelivery::query()
            ->where('user_id', $staffUser->id)
            ->where('metadata->access_code', $staffUser->access_code)
            ->exists())->toBeTrue();
});

test('department heads can open their staff spreadsheet import page', function () {
    $department = Department::factory()->create([
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $department->load('faculty');
    $hodUser = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $this->actingAs($hodUser)->get(route('dashboard.department-staff.import'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'staff')
            ->where('dashboard.hodStaffForm.mode', 'import')
            ->where('dashboard.hodStaffForm.department.abbreviation', 'CPE')
            ->etc());
});

test('department heads can import staffs for their department', function () {
    $faculty = Faculty::factory()->create([
        'abbreviation' => 'SESET',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $response = $this->actingAs($hodUser)->post(route('dashboard.department-staff.import.store'), [
        'file_name' => 'cpe-staff.xlsx',
        'rows' => [
            [
                'name' => 'Ifeoma Nwosu',
                'email' => 'ifeoma.nwosu@example.com',
                'accessCode' => '',
            ],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));
    $response->assertSessionHas('success', 'Imported 1 staff record from spreadsheet.');

    $staffUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'ifeoma.nwosu@example.com')
        ->firstOrFail();

    expect($staffUser->account_type)->toBe(AccountType::Staff)
        ->and($staffUser->name)->toBe('Ifeoma Nwosu')
        ->and($staffUser->access_code)->toStartWith('SESET-CPE-')
        ->and($staffUser->staffProfile->department_id)->toBe($department->id)
        ->and(ImportBatch::query()
            ->where('file_name', 'cpe-staff.xlsx')
            ->where('type', 'hod_staff_import')
            ->where('scope_type', Department::class)
            ->where('scope_id', $department->id)
            ->exists())->toBeTrue()
        ->and(EmailDelivery::query()
            ->where('user_id', $staffUser->id)
            ->where('metadata->access_code', $staffUser->access_code)
            ->exists())->toBeTrue();
});

test('department heads cannot import existing staff from another department', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'CPE',
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'EEE',
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);
    $existingStaff = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'email' => 'staff.eee@example.com',
    ]);

    StaffProfile::factory()->create([
        'user_id' => $existingStaff->id,
        'department_id' => $otherDepartment->id,
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $this->actingAs($hodUser)->from(route('dashboard.department-staff.import'))
        ->post(route('dashboard.department-staff.import.store'), [
            'file_name' => 'cpe-staff.xlsx',
            'rows' => [
                [
                    'name' => 'Existing Staff',
                    'email' => 'staff.eee@example.com',
                    'accessCode' => '',
                ],
            ],
        ])
        ->assertRedirect(route('dashboard.department-staff.import'))
        ->assertSessionHasErrors([
            'rows.0.email' => 'This staff member already belongs to EEE.',
        ]);

    expect($existingStaff->staffProfile->refresh()->department_id)->toBe($otherDepartment->id);
});

test('faculty heads can import hods from spreadsheet rows', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.import.store'), [
        'file_name' => 'hods.xlsx',
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Nkem Okorie',
                'email' => 'nkem.okorie@example.com',
                'accessCode' => 'SESET-CPE-IMPORT',
            ],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'departments',
    ]));
    $response->assertSessionHas('success', 'Imported 1 HOD assignment from spreadsheet.');

    $hodUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'nkem.okorie@example.com')
        ->firstOrFail();

    expect($hodUser->account_type)->toBe(AccountType::Staff)
        ->and($hodUser->access_code)->toBe('SESET-CPE-IMPORT')
        ->and($hodUser->staffProfile->department_id)->toBe($department->id)
        ->and($department->refresh()->hod_staff_profile_id)->toBe($hodUser->staffProfile->id)
        ->and(ImportBatch::query()->where('file_name', 'hods.xlsx')->where('type', 'hod_import')->exists())->toBeTrue()
        ->and(EmailDelivery::query()->where('user_id', $hodUser->id)->where('status', 'skipped')->exists())->toBeTrue();
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
        ->has('dashboard.staffHome.assignedSets', 0)
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('staff home is scoped to assigned student sets', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $assignedLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $assignedEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $otherEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2022/2023',
        'starts_on' => '2022-09-01',
        'ends_on' => '2023-08-31',
    ]);
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($assignedEntrySession, $staffProfile, $currentSession);

    foreach ([
        ['name' => 'Ada Nwosu', 'registration_number' => '2023001001'],
        ['name' => 'Chidera Eze', 'registration_number' => '2023001002'],
    ] as $student) {
        $studentUser = User::factory()->student()->create([
            'school_id' => $school->id,
            'name' => $student['name'],
        ]);

        StudentProfile::factory()->create([
            'school_id' => $school->id,
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'current_academic_year_id' => $assignedLevel->id,
            'entry_academic_session_id' => $assignedEntrySession->id,
            'registration_number' => $student['registration_number'],
        ]);
    }

    $outsideUser = User::factory()->student()->create([
        'school_id' => $school->id,
        'name' => 'Ifeoma Obi',
    ]);

    StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $outsideUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $assignedLevel->id,
        'entry_academic_session_id' => $otherEntrySession->id,
        'registration_number' => '2022001003',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'home')
        ->has('dashboard.staffHome', fn (Assert $page) => $page
            ->has('assignedSets', 1)
            ->where('assignedSets.0.entrySession.name', '2023/2024')
            ->has('levels', 5)
            ->has('students', 2)
            ->where('students.0.fullName', 'Ada Nwosu')
            ->where('students.0.registrationNumber', '2023001001')
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
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $assignedLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $assignedEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $otherEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2022/2023',
        'starts_on' => '2022-09-01',
        'ends_on' => '2023-08-31',
    ]);
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($assignedEntrySession, $staffProfile, $currentSession);

    foreach ([
        ['name' => 'Chidera Eze', 'email' => 'chidera.eze@example.com', 'registration_number' => '2023001002'],
        ['name' => 'Ada Nwosu', 'email' => 'ada.nwosu@example.com', 'registration_number' => '2023001001'],
    ] as $student) {
        $studentUser = User::factory()->student()->create([
            'school_id' => $school->id,
            'name' => $student['name'],
            'email' => $student['email'],
        ]);

        StudentProfile::factory()->create([
            'school_id' => $school->id,
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'current_academic_year_id' => $assignedLevel->id,
            'entry_academic_session_id' => $assignedEntrySession->id,
            'registration_number' => $student['registration_number'],
        ]);
    }

    $outsideUser = User::factory()->student()->create([
        'school_id' => $school->id,
        'name' => 'Ifeoma Obi',
    ]);

    StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $outsideUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $assignedLevel->id,
        'entry_academic_session_id' => $otherEntrySession->id,
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
            ->where('department.abbreviation', $department->abbreviation)
            ->has('assignedSets', 1)
            ->where('assignedSets.0.entrySession.name', '2023/2024')
            ->where('totalStudents', 2)
            ->has('students', 2)
            ->where('students.0.fullName', 'Ada Nwosu')
            ->where('students.0.email', 'ada.nwosu@example.com')
            ->where('students.0.registrationNumber', '2023001001')
            ->where('students.0.currentLevel.title', '300 Level')
            ->where('students.0.profileUrl', fn (string $url): bool => str_contains($url, '/dashboard/students/'))
            ->has('students.0.pendingCarryovers', 1)
            ->where('students.0.pendingCarryovers.0', 'MTH 201')
            ->has('students.0.levelGpas', 3)
            ->where('students.0.levelGpas.0.level', '100L')
            ->where('students.0.cgpa', '3.55')
            ->where('students.1.fullName', 'Chidera Eze')
            ->etc()
        )
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('department heads see the hod dashboard shell', function () {
    $department = Department::factory()->create();
    $department->load('faculty');
    $user = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
    ]);
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
        ->where('dashboard.hod.department.name', $department->name)
        ->where('dashboard.hod.metrics.students', 0)
        ->where('dashboard.hod.staff.0.role', 'HOD')
        ->where('dashboard.hod.staff.0.roleContext', $department->abbreviation)
        ->etc());
});

test('department heads use student results instead of reports', function () {
    $department = Department::factory()->create();
    $user = User::factory()->staff()->create();
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($staffProfile);

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'student-results',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'student-results')
            ->where('dashboard.sectionTitle', 'Student Results')
            ->where('dashboard.hod.studentResults.studentsCount', 0)
            ->etc());

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'reports',
    ]))->assertNotFound();
});

test('department heads can view student profiles in their department', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $department->faculty->school_id,
        'name' => '2023/2024',
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);
    $studentUser = User::factory()->student()->create([
        'school_id' => $department->faculty->school_id,
        'name' => 'Ada Nwosu',
        'email' => 'ada.nwosu@example.com',
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'user_id' => $studentUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => $entrySession->id,
        'registration_number' => '2023001001',
    ]);
    $outsideProfile = StudentProfile::factory()->create([
        'current_academic_year_id' => $currentLevel->id,
        'registration_number' => '2023001002',
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $this->actingAs($hodUser)->get(route('dashboard.students.show', $studentProfile))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'students')
            ->where('dashboard.studentRecord.student.fullName', 'Ada Nwosu')
            ->where('dashboard.studentRecord.student.registrationNumber', '2023001001')
            ->where('dashboard.studentRecord.entrySession.name', '2023/2024')
            ->has('dashboard.studentRecord.semesters', 6)
            ->etc());

    $this->actingAs($hodUser)->get(route('dashboard.students.show', $outsideProfile))
        ->assertNotFound();
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
