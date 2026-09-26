<?php

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\StudentSet;
use Database\Seeders\AcademicStructureSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('a course adviser builds a set schedule from sessions through courses', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'department_name' => 'Computer Engineering',
        'department_slug' => 'computer-engineering',
        'entry_start_year' => 2025,
        'entry_end_year' => 2026,
    ]);
    $level = AcademicYear::query()->where('year_number', 1)->firstOrFail();
    $firstSemester = Semester::query()->where('slug', 'first')->firstOrFail();

    $this->actingAs($advisor->user)->post(route('student-sets.sessions.store', $set->routeParameters()), [
        'academic_year_id' => $level->id,
        'start_year' => 2025,
        'first_semester_maximum_course_units' => 24,
        'second_semester_maximum_course_units' => 24,
    ])->assertSessionHasNoErrors();

    $session = AcademicSession::query()->whereBelongsTo($set)->firstOrFail();
    $semester = AcademicSessionSemester::query()
        ->whereBelongsTo($session)
        ->whereBelongsTo($firstSemester)
        ->firstOrFail();
    expect($session->semesterInstances)->toHaveCount(2);
    $this->post(route('student-sets.courses.store', [
        ...$set->routeParameters(),
        'sessionYears' => $session->routeYears(),
        'semester' => $semester->semester->slug,
    ]), [
        'code' => 'cpe 101',
        'title' => 'Introduction to Computer Engineering',
        'credit_units' => 3,
        'is_elective' => false,
        'prerequisite_course_ids' => [],
    ])->assertSessionHasNoErrors();

    $offering = CourseOffering::query()->with('course')->firstOrFail();
    expect($offering->course->student_set_id)->toBe($set->id)
        ->and($offering->course->code)->toBe('CPE 101')
        ->and($offering->course->urlCode())->toBe('cpe101')
        ->and($offering->is_elective)->toBeFalse()
        ->and($offering->academic_session_semester_id)->toBe($semester->id);

    $courseRoute = [
        ...$set->routeParameters(),
        'sessionYears' => $session->routeYears(),
        'semester' => 'first',
        'courseCode' => $offering->course->urlCode(),
    ];
    $this->get(route('student-sets.courses.edit', $courseRoute))->assertOk();
    $this->get(route('student-sets.results.course', $courseRoute))->assertOk();

    $this->patch(route('student-sets.semesters.registration.update', [
        ...$set->routeParameters(),
        'sessionYears' => $session->routeYears(),
        'semester' => $semester->semester->slug,
    ]), ['registration_enabled' => true])->assertSessionHasNoErrors();

    $this->get(route('student-sets.courses.show', [
        ...$set->routeParameters(),
        'session' => $session->routeYears(),
        'semester' => $semester->semester->slug,
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.sectionTitle', 'Courses & Results')
        ->where('dashboard.adviserCourseSchedules.selectedSet.id', $set->id)
        ->where('dashboard.adviserCourseSchedules.selectedSemester.maximumCourseUnits', 24)
        ->where('dashboard.adviserCourseSchedules.selectedSemester.registrationEnabled', true)
        ->where('dashboard.adviserCourseSchedules.selectedSemester.offerings.0.code', 'CPE 101'));
});

test('courses stay within their set and can be offered again in a later semester', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $otherSet = StudentSet::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'department_name' => 'Electrical Engineering',
        'department_slug' => 'electrical-engineering',
    ]);
    $firstYear = AcademicYear::query()->where('year_number', 1)->firstOrFail();
    $secondYear = AcademicYear::query()->where('year_number', 2)->firstOrFail();
    $semesterTemplate = Semester::query()->where('slug', 'first')->firstOrFail();
    $firstSession = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $firstYear->id,
        'start_year' => 2024,
        'end_year' => 2025,
    ]);
    $secondSession = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $secondYear->id,
        'start_year' => 2025,
        'end_year' => 2026,
    ]);
    $firstSemester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $firstSession->id,
        'semester_id' => $semesterTemplate->id,
    ]);
    $secondSemester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $secondSession->id,
        'semester_id' => $semesterTemplate->id,
    ]);
    $course = Course::factory()->create([
        'student_set_id' => $set->id,
        'code' => 'CPE 101',
    ]);
    CourseOffering::factory()->create([
        'course_id' => $course->id,
        'academic_session_semester_id' => $firstSemester->id,
    ]);

    $this->actingAs($advisor->user)->post(route('student-sets.courses.store', [
        ...$set->routeParameters(),
        'sessionYears' => $secondSession->routeYears(),
        'semester' => $secondSemester->semester->slug,
    ]), [
        'code' => 'CPE 101',
        'title' => 'Introduction to Computer Engineering',
        'credit_units' => 3,
        'is_elective' => true,
        'prerequisite_course_ids' => [],
    ])->assertSessionHasNoErrors();

    expect(Course::query()->where('student_set_id', $set->id)->where('code', 'CPE 101')->count())->toBe(1)
        ->and($course->offerings()->count())->toBe(2);

    $this->post(route('student-sets.courses.store', [
        ...$otherSet->routeParameters(),
        'sessionYears' => $secondSession->routeYears(),
        'semester' => $secondSemester->semester->slug,
    ]), [
        'code' => 'CPE 201',
        'title' => 'Digital Systems',
        'credit_units' => 3,
        'is_elective' => false,
        'prerequisite_course_ids' => [],
    ])->assertNotFound();
});

test('session settings protect current sessions and sessions with academic records', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $firstYear = AcademicYear::query()->where('year_number', 1)->firstOrFail();
    $secondYear = AcademicYear::query()->where('year_number', 2)->firstOrFail();
    $set = StudentSet::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'current_academic_year_id' => $firstYear->id,
    ]);
    $firstSession = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $firstYear->id,
        'start_year' => 2024,
        'end_year' => 2025,
    ]);
    $secondSession = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $secondYear->id,
        'start_year' => 2025,
        'end_year' => 2026,
    ]);
    $semester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $firstSession->id,
    ]);
    $course = Course::factory()->create(['student_set_id' => $set->id]);
    CourseOffering::factory()->create([
        'course_id' => $course->id,
        'academic_session_semester_id' => $semester->id,
    ]);

    $this->actingAs($advisor->user)
        ->delete(route('student-sets.sessions.destroy', [
            ...$set->routeParameters(),
            'sessionYears' => $firstSession->routeYears(),
        ]))
        ->assertSessionHas('error');

    $this->patch(route('student-sets.sessions.update', [
        ...$set->routeParameters(),
        'sessionYears' => $firstSession->routeYears(),
    ]), [
        'academic_year_id' => $firstYear->id,
        'start_year' => 2023,
        'first_semester_maximum_course_units' => 24,
        'second_semester_maximum_course_units' => 24,
    ])->assertSessionHasNoErrors();

    expect($firstSession->refresh()->label())->toBe('2023/2024')
        ->and($semester->fresh()->academic_session_id)->toBe($firstSession->id);

    $this->patch(route('student-sets.update', $set->routeParameters()), [
        'student_signup_enabled' => true,
        'current_academic_year_id' => $secondYear->id,
    ])->assertSessionHasNoErrors();

    $this->delete(route('student-sets.sessions.destroy', [
        ...$set->routeParameters(),
        'sessionYears' => $firstSession->routeYears(),
    ]))->assertSessionHas('error');

    $this->patch(route('student-sets.update', $set->routeParameters()), [
        'student_signup_enabled' => true,
        'current_academic_year_id' => $firstYear->id,
    ])->assertSessionHasNoErrors();

    $this->delete(route('student-sets.sessions.destroy', [
        ...$set->routeParameters(),
        'sessionYears' => $secondSession->routeYears(),
    ]))->assertRedirect(route('student-sets.edit', $set->routeParameters()));

    expect($secondSession->fresh())->toBeNull();
});
