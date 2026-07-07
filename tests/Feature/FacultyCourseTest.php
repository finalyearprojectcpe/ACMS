<?php

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function facultyCourseContext(): array
{
    test()->seed(AcademicStructureSeeder::class);

    $faculty = Faculty::factory()->create([
        'name' => 'School of Engineering and Engineering Technology',
        'abbreviation' => 'SESET',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
        'minimum_years_to_degree' => 5,
    ]);
    $academicSession = AcademicSession::factory()->create([
        'school_id' => $faculty->school_id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
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

    $faculty->assignHead($facultyHeadProfile);

    return [
        'faculty' => $faculty->refresh(),
        'department' => $department,
        'academicSession' => $academicSession,
        'facultyHeadUser' => $facultyHeadUser,
        'facultyHeadProfile' => $facultyHeadProfile,
    ];
}

function yearSemester(string $academicYearSlug, string $semesterSlug): AcademicYearSemester
{
    return AcademicYearSemester::query()
        ->whereBelongsTo(AcademicYear::query()->where('slug', $academicYearSlug)->firstOrFail())
        ->whereBelongsTo(Semester::query()->where('slug', $semesterSlug)->firstOrFail())
        ->firstOrFail();
}

function createFacultyCourseOffering(
    Faculty $faculty,
    AcademicSession $academicSession,
    string $code,
    string $academicYearSlug,
    string $semesterSlug,
    int $units = 3,
): CourseOffering {
    $course = Course::factory()->create([
        'school_id' => $faculty->school_id,
        'code' => $code,
        'title' => "{$code} Course",
        'slug' => str($code.' course')->slug()->toString(),
        'credit_units' => $units,
    ]);

    return $faculty->courseOfferings()->create([
        'course_id' => $course->id,
        'academic_session_id' => $academicSession->id,
        'academic_year_semester_id' => yearSemester($academicYearSlug, $semesterSlug)->id,
        'is_mandatory' => true,
    ]);
}

test('faculty heads can view faculty courses', function () {
    $context = facultyCourseContext();

    createFacultyCourseOffering(
        $context['faculty'],
        $context['academicSession'],
        'GST 101',
        '100-level',
        'harmattan',
        2,
    );

    $response = $this->actingAs($context['facultyHeadUser'])->get(route('dashboard.section', [
        'section' => 'sessions-courses',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'faculty_head')
        ->where('dashboard.section', 'sessions-courses')
        ->where('dashboard.sectionTitle', 'Sessions & Courses')
        ->where('dashboard.facultyHeadCourses.academicSession.name', '2025/2026')
        ->where('dashboard.facultyHeadCourses.academicSession.status', 'Current')
        ->where('dashboard.facultyHeadCourses.metrics.departments', 1)
        ->where('dashboard.facultyHeadCourses.metrics.courses', 1)
        ->where('dashboard.facultyHeadCourses.metrics.compulsoryUnits', 2)
        ->has('dashboard.facultyHeadCourses.levels', 5)
        ->where('dashboard.facultyHeadCourses.levels.0.slug', '100-level')
        ->where('dashboard.facultyHeadCourses.semesters.0.level.slug', '100-level')
        ->where('dashboard.facultyHeadCourses.semesters.0.semester.slug', 'harmattan')
        ->where('dashboard.facultyHeadCourses.semesters.0.offerings.0.code', 'GST 101')
        ->where('dashboard.facultyHeadCourses.semesters.0.offerings.0.departmentsCount', 1)
        ->etc());
});

test('faculty heads can create faculty courses with faculty prerequisites', function () {
    $context = facultyCourseContext();
    $prerequisiteOffering = createFacultyCourseOffering(
        $context['faculty'],
        $context['academicSession'],
        'GST 101',
        '100-level',
        'harmattan',
        2,
    );

    $response = $this->actingAs($context['facultyHeadUser'])->post(route('dashboard.sessions-courses.store', [
        'academicSession' => $context['academicSession']->id,
        'level' => '100-level',
        'semester' => 'rain',
    ]), [
        'code' => 'GST 102',
        'title' => 'Use of English II',
        'credit_units' => 2,
        'prerequisite_course_ids' => [$prerequisiteOffering->course_id],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'sessions-courses',
        'academic_session' => $context['academicSession']->id,
    ]));
    $response->assertSessionHas('success', 'Faculty course saved.');

    $course = Course::query()
        ->where('school_id', $context['faculty']->school_id)
        ->where('code', 'GST 102')
        ->firstOrFail();
    $offering = CourseOffering::query()
        ->where('owner_type', Faculty::class)
        ->where('owner_id', $context['faculty']->id)
        ->where('course_id', $course->id)
        ->firstOrFail();

    expect($course->title)->toBe('Use of English II')
        ->and($course->credit_units)->toBe(2)
        ->and($course->prerequisites()->whereKey($prerequisiteOffering->course_id)->exists())->toBeTrue()
        ->and($offering->is_mandatory)->toBeTrue();
});

test('faculty heads can delete faculty course offerings', function () {
    $context = facultyCourseContext();
    $offering = createFacultyCourseOffering(
        $context['faculty'],
        $context['academicSession'],
        'GST 101',
        '100-level',
        'harmattan',
        2,
    );
    $course = $offering->course;

    $response = $this->actingAs($context['facultyHeadUser'])->delete(route('dashboard.sessions-courses.destroy', [
        'academicSession' => $context['academicSession']->id,
        'level' => '100-level',
        'semester' => 'harmattan',
        'offering' => $offering->id,
    ]));

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'sessions-courses',
        'academic_session' => $context['academicSession']->id,
    ]));
    $response->assertSessionHas('success', 'Faculty course removed.');

    expect(CourseOffering::query()->whereKey($offering->id)->exists())->toBeFalse()
        ->and(Course::query()->whereKey($course->id)->exists())->toBeTrue();
});

test('faculty heads can view courses for a selected academic session', function () {
    $context = facultyCourseContext();
    $upcomingSession = AcademicSession::factory()->create([
        'school_id' => $context['faculty']->school_id,
        'name' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
    ]);

    createFacultyCourseOffering(
        $context['faculty'],
        $upcomingSession,
        'GST 201',
        '200-level',
        'harmattan',
        2,
    );

    $response = $this->actingAs($context['facultyHeadUser'])->get(route('dashboard.section', [
        'section' => 'sessions-courses',
        'academic_session' => $upcomingSession->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.facultyHeadCourses.academicSession.name', '2026/2027')
        ->where('dashboard.facultyHeadCourses.academicSession.status', 'Upcoming')
        ->where('dashboard.facultyHeadCourses.semesters.2.offerings.0.code', 'GST 201')
        ->etc());
});

test('faculty heads can manage faculty courses for past academic sessions', function () {
    $context = facultyCourseContext();
    $pastSession = AcademicSession::factory()->create([
        'school_id' => $context['faculty']->school_id,
        'name' => '2024/2025',
        'starts_on' => '2024-09-01',
        'ends_on' => '2025-08-31',
    ]);

    $viewResponse = $this->actingAs($context['facultyHeadUser'])->get(route('dashboard.section', [
        'section' => 'sessions-courses',
        'academic_session' => $pastSession->id,
    ]));

    $viewResponse->assertOk();
    $viewResponse->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.facultyHeadCourses.academicSession.name', '2024/2025')
        ->where('dashboard.facultyHeadCourses.academicSession.status', 'Past')
        ->where('dashboard.facultyHeadCourses.canManageSelectedSession', true)
        ->etc());

    $storeResponse = $this->actingAs($context['facultyHeadUser'])->post(route('dashboard.sessions-courses.store', [
        'academicSession' => $pastSession->id,
        'level' => '100-level',
        'semester' => 'harmattan',
    ]), [
        'code' => 'GST 101',
        'title' => 'Use of English I',
        'credit_units' => 2,
        'prerequisite_course_ids' => [],
    ]);

    $storeResponse->assertRedirect(route('dashboard.section', [
        'section' => 'sessions-courses',
        'academic_session' => $pastSession->id,
    ]));
    $storeResponse->assertSessionHas('success', 'Faculty course saved.');

    expect(CourseOffering::query()
        ->where('owner_type', Faculty::class)
        ->where('owner_id', $context['faculty']->id)
        ->where('academic_session_id', $pastSession->id)
        ->whereHas('course', fn ($query) => $query->where('code', 'GST 101'))
        ->exists())->toBeTrue();
});

test('faculty heads cannot create academic sessions', function () {
    $context = facultyCourseContext();

    $response = $this->actingAs($context['facultyHeadUser'])->post(route('dashboard.academic-sessions.store'), [
        'name' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
    ]);

    $response->assertForbidden();
});

test('faculty heads can copy faculty courses from the previous session', function () {
    $context = facultyCourseContext();
    $futureSession = AcademicSession::factory()->create([
        'school_id' => $context['faculty']->school_id,
        'name' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
    ]);
    $sourceOffering = createFacultyCourseOffering(
        $context['faculty'],
        $context['academicSession'],
        'GST 101',
        '100-level',
        'harmattan',
        2,
    );

    $response = $this->actingAs($context['facultyHeadUser'])->post(route('dashboard.sessions-courses.copy-courses', [
        'academicSession' => $futureSession->id,
    ]));

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'sessions-courses',
        'academic_session' => $futureSession->id,
    ]));
    $response->assertSessionHas('success', '1 faculty courses copied from 2025/2026.');

    expect(CourseOffering::query()
        ->where('owner_type', Faculty::class)
        ->where('owner_id', $context['faculty']->id)
        ->where('academic_session_id', $futureSession->id)
        ->where('course_id', $sourceOffering->course_id)
        ->exists())->toBeTrue();
});

test('faculty heads can copy faculty courses for one semester from the previous session', function () {
    $context = facultyCourseContext();
    $futureSession = AcademicSession::factory()->create([
        'school_id' => $context['faculty']->school_id,
        'name' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
    ]);
    $harmattanOffering = createFacultyCourseOffering(
        $context['faculty'],
        $context['academicSession'],
        'GST 101',
        '100-level',
        'harmattan',
        2,
    );
    $rainOffering = createFacultyCourseOffering(
        $context['faculty'],
        $context['academicSession'],
        'GST 102',
        '100-level',
        'rain',
        2,
    );

    $response = $this->actingAs($context['facultyHeadUser'])->post(route('dashboard.sessions-courses.copy-semester-courses', [
        'academicSession' => $futureSession->id,
        'level' => '100-level',
        'semester' => 'harmattan',
    ]));

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'sessions-courses',
        'academic_session' => $futureSession->id,
    ]));
    $response->assertSessionHas('success', '1 faculty courses copied into 100 Level Harmattan from 2025/2026.');

    expect(CourseOffering::query()
        ->where('owner_type', Faculty::class)
        ->where('owner_id', $context['faculty']->id)
        ->where('academic_session_id', $futureSession->id)
        ->where('course_id', $harmattanOffering->course_id)
        ->exists())->toBeTrue()
        ->and(CourseOffering::query()
            ->where('owner_type', Faculty::class)
            ->where('owner_id', $context['faculty']->id)
            ->where('academic_session_id', $futureSession->id)
            ->where('course_id', $rainOffering->course_id)
            ->exists())->toBeFalse();
});

test('course catalog codes are scoped to each school', function () {
    $firstSchool = School::factory()->create();
    $secondSchool = School::factory()->create();

    Course::factory()->create([
        'school_id' => $firstSchool->id,
        'code' => 'GST 101',
        'title' => 'Use of English I',
        'slug' => 'gst-101-use-of-english-i',
    ]);
    Course::factory()->create([
        'school_id' => $secondSchool->id,
        'code' => 'GST 101',
        'title' => 'Use of English I',
        'slug' => 'gst-101-use-of-english-i',
    ]);

    expect(Course::query()->where('code', 'GST 101')->count())->toBe(2);
});

test('staff course schedules show faculty courses as read only', function () {
    $context = facultyCourseContext();
    $staffUser = User::factory()->staff()->create([
        'school_id' => $context['faculty']->school_id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $context['department']->id,
    ]);

    $context['department']->assignAdvisorToStudentSet(
        $context['academicSession'],
        $staffProfile,
        $context['academicSession'],
    );

    createFacultyCourseOffering(
        $context['faculty'],
        $context['academicSession'],
        'GST 101',
        '100-level',
        'harmattan',
        2,
    );

    $response = $this->actingAs($staffUser)->get(route('dashboard.section', [
        'section' => 'courses',
        'level' => '100-level',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.staffCourseSchedules.semesters.0.offerings.0.code', 'GST 101')
        ->where('dashboard.staffCourseSchedules.semesters.0.offerings.0.courseType', 'Faculty Course')
        ->where('dashboard.staffCourseSchedules.semesters.0.offerings.0.canEdit', false)
        ->etc());
});

test('staff users cannot manage faculty courses', function () {
    $context = facultyCourseContext();
    $offering = createFacultyCourseOffering(
        $context['faculty'],
        $context['academicSession'],
        'GST 101',
        '100-level',
        'harmattan',
        2,
    );
    $staffUser = User::factory()->staff()->create([
        'school_id' => $context['faculty']->school_id,
    ]);

    StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $context['department']->id,
    ]);

    $this->actingAs($staffUser)->get(route('dashboard.sessions-courses.create', [
        'academicSession' => $context['academicSession']->id,
        'level' => '100-level',
        'semester' => 'harmattan',
    ]))->assertNotFound();

    $this->actingAs($staffUser)->post(route('dashboard.sessions-courses.store', [
        'academicSession' => $context['academicSession']->id,
        'level' => '100-level',
        'semester' => 'harmattan',
    ]), [
        'code' => 'GST 101',
        'title' => 'Use of English I',
        'credit_units' => 2,
        'prerequisite_course_ids' => [],
    ])->assertNotFound();

    $this->actingAs($staffUser)->delete(route('dashboard.sessions-courses.destroy', [
        'academicSession' => $context['academicSession']->id,
        'level' => '100-level',
        'semester' => 'harmattan',
        'offering' => $offering->id,
    ]))->assertNotFound();
});

test('faculty courses reject department owned prerequisites', function () {
    $context = facultyCourseContext();
    $departmentCourse = Course::factory()->create([
        'school_id' => $context['faculty']->school_id,
        'code' => 'CPE 101',
        'title' => 'Introduction to Computer Engineering',
        'slug' => 'cpe-101-introduction-to-computer-engineering',
        'credit_units' => 2,
    ]);

    $context['department']->courseOfferings()->create([
        'course_id' => $departmentCourse->id,
        'academic_session_id' => $context['academicSession']->id,
        'academic_year_semester_id' => yearSemester('100-level', 'harmattan')->id,
        'is_mandatory' => true,
    ]);

    $response = $this->actingAs($context['facultyHeadUser'])->post(route('dashboard.sessions-courses.store', [
        'academicSession' => $context['academicSession']->id,
        'level' => '100-level',
        'semester' => 'rain',
    ]), [
        'code' => 'GST 102',
        'title' => 'Use of English II',
        'credit_units' => 2,
        'prerequisite_course_ids' => [$departmentCourse->id],
    ]);

    $response->assertSessionHasErrors('prerequisite_course_ids');
});
