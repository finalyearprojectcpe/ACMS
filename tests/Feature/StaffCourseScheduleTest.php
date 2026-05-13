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
        ->where('dashboard.sectionTitle', '300 Level Course Schedules')
        ->has('dashboard.levels', 1)
        ->where('dashboard.levels.0.slug', '300-level')
        ->has('dashboard.courseScheduleLevels', 3)
        ->where('dashboard.courseScheduleLevels.0.slug', '100-level')
        ->where('dashboard.courseScheduleLevels.2.slug', '300-level')
        ->has('dashboard.staffCourseSchedules', fn (Assert $page) => $page
            ->where('level.title', '300 Level')
            ->has('semesters', 2)
            ->where('semesters.0.name', 'Harmattan')
            ->where('semesters.0.totalUnits', 14)
            ->where('semesters.0.mandatoryCount', 4)
            ->where('semesters.0.optionalCount', 1)
            ->has('semesters.0.offerings', 5)
            ->where('semesters.0.offerings.0.code', 'CPE 301')
            ->where('semesters.0.offerings.0.requirement', 'Mandatory')
            ->where('semesters.0.offerings.0.timetableSlotsCount', 1)
            ->where('semesters.0.offerings.0.prerequisites.0.code', 'CPE 202')
            ->where('semesters.0.offerings.4.code', 'MTH 305')
            ->where('semesters.0.offerings.4.requirement', 'Optional')
        )
        ->etc());
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

test('course timetable slots cannot reuse a venue at an overlapping time', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'staff4@futo.edu')->firstOrFail();
    $targetOffering = CourseOffering::query()
        ->whereHas('course', fn ($query) => $query->where('code', 'CPE 303'))
        ->firstOrFail();

    $response = $this->actingAs($user)->post(route('dashboard.courses.timetable.store', [
        'level' => '300-level',
        'semester' => 'harmattan',
        'offering' => $targetOffering->id,
    ]), [
        'day_of_week' => 'Monday',
        'starts_at' => '09:00',
        'ends_at' => '11:00',
        'venue' => 'ETF 204',
    ]);

    $response->assertSessionHasErrors('venue');
});
