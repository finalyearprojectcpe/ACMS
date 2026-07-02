<?php

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('course advisors see course schedules up to their assigned level', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'staff4@futo.edu')->firstOrFail();

    $response = $this->actingAs($user)->get(route('dashboard.level', [
        'section' => 'courses',
        'level' => '300-level',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'courses')
        ->where('dashboard.sectionTitle', '300 Level Course Offerings')
        ->has('dashboard.levels', 1)
        ->where('dashboard.levels.0.slug', '300-level')
        ->has('dashboard.courseScheduleLevels', 3)
        ->where('dashboard.courseScheduleLevels.0.slug', '100-level')
        ->where('dashboard.courseScheduleLevels.2.slug', '300-level')
        ->has('dashboard.staffCourseSchedules', fn (Assert $page) => $page
            ->where('level.title', '300 Level')
            ->where('academicSession.name', '2025/2026')
            ->has('semesters', 2)
            ->where('semesters.0.name', 'Harmattan')
            ->where('semesters.0.totalUnits', 14)
            ->where('semesters.0.facultyCourseCount', 2)
            ->where('semesters.0.departmentalCourseCount', 3)
            ->where('semesters.0.electiveCourseCount', 0)
            ->has('semesters.0.offerings', 5)
            ->where('semesters.0.offerings.0.code', 'CPE 301')
            ->where('semesters.0.offerings.0.courseType', 'Departmental Course')
            ->where('semesters.0.offerings.0.prerequisites.0.code', 'CPE 202')
            ->where('semesters.0.offerings.4.code', 'MTH 305')
            ->where('semesters.0.offerings.4.courseType', 'Faculty Course')
        )
        ->etc());
});

test('department heads view course offerings but cannot manage them', function () {
    $this->seed(DatabaseSeeder::class);

    $hodUser = User::query()->where('email', 'staff1@futo.edu')->firstOrFail();
    $offering = CourseOffering::query()
        ->where('owner_type', Department::class)
        ->whereHas('course', fn ($query) => $query->where('code', 'CPE 301'))
        ->firstOrFail();

    $response = $this->actingAs($hodUser)->get(route('dashboard.section', [
        'section' => 'courses',
        'academic_session' => $offering->academic_session_id,
        'level' => '300-level',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'hod')
        ->where('dashboard.section', 'courses')
        ->where('dashboard.staffCourseSchedules', null)
        ->where('dashboard.staffCourseOfferingForm', null)
        ->where('dashboard.hod.courseOfferings.level.slug', '300-level')
        ->where('dashboard.hod.courseOfferings.semesters.0.offerings.0.courseType', 'Departmental Course')
        ->where('dashboard.hod.staff', fn ($staff): bool => collect($staff)->contains(
            fn ($member): bool => $member['email'] === 'staff4@futo.edu'
                && $member['role'] === 'Course Adviser'
                && $member['roleContext'] === '300L',
        ))
        ->etc());

    $this->actingAs($hodUser)->get(route('dashboard.courses.create', [
        'level' => '300-level',
        'semester' => 'harmattan',
    ]))->assertNotFound();

    $this->actingAs($hodUser)->post(route('dashboard.courses.store', [
        'level' => '300-level',
        'semester' => 'harmattan',
    ]), [
        'code' => 'CPE 399',
        'title' => 'Special Topics',
        'credit_units' => 2,
        'is_mandatory' => true,
        'prerequisite_course_ids' => [],
    ])->assertNotFound();

    $this->actingAs($hodUser)->get(route('dashboard.courses.edit', [
        'level' => '300-level',
        'semester' => 'harmattan',
        'offering' => $offering->id,
    ]))->assertNotFound();
});

test('course advisors can create a department course offering with prerequisites', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'staff4@futo.edu')->firstOrFail();
    $department = Department::query()->where('abbreviation', 'CPE')->firstOrFail();
    $prerequisite = Course::query()->where('code', 'CPE 301')->firstOrFail();

    $response = $this->actingAs($user)->post(route('dashboard.courses.store', [
        'level' => '300-level',
        'semester' => 'rain',
    ]), [
        'code' => 'CPE 309',
        'title' => 'Computer Engineering Laboratory',
        'credit_units' => 1,
        'is_mandatory' => false,
        'prerequisite_course_ids' => [$prerequisite->id],
    ]);

    $response->assertRedirect(route('dashboard.level', [
        'section' => 'courses',
        'level' => '300-level',
    ]));

    $course = Course::query()->where('code', 'CPE 309')->firstOrFail();
    $offering = CourseOffering::query()
        ->where('owner_type', Department::class)
        ->where('owner_id', $department->id)
        ->where('course_id', $course->id)
        ->firstOrFail();

    expect($course->credit_units)->toBe(1)
        ->and($offering->is_mandatory)->toBeFalse()
        ->and($course->prerequisites()->where('code', 'CPE 301')->exists())->toBeTrue();
});
