<?php

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Database\Seeders\AcademicStructureSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('an adviser opens registrations by set and session without exposing record ids', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $otherAdvisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $session = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => AcademicYear::query()->where('year_number', 1)->firstOrFail()->id,
        'start_year' => 2025,
        'end_year' => 2026,
    ]);
    AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
    ]);
    $url = route('student-sets.registrations.show', [
        ...$set->routeParameters(),
        'session' => '20252026',
        'semester' => 'first',
    ]);

    $this->actingAs($advisor->user)->get($url)->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.activeStudentSetId', $set->id)
            ->where('dashboard.adviserCourseRegistration.selectedSet.id', $set->id));

    $this->actingAs($otherAdvisor->user)->get($url)->assertNotFound();
});

test('a student submits selected courses and their adviser reviews the registration', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
    ]);
    $session = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => AcademicYear::query()->where('year_number', 1)->firstOrFail()->id,
        'start_year' => 2025,
        'end_year' => 2026,
    ]);
    $semester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
        'maximum_course_units' => 5,
        'registration_enabled' => true,
    ]);
    $normal = registrationOffering($set, $semester, 'CPE 101', false, 3);
    $elective = registrationOffering($set, $semester, 'CPE 103', true, 2);

    $this->actingAs($student->user)->post(route('dashboard.course-registration.store'), [
        'action' => 'submit',
        'items' => [
            ['course_offering_id' => $normal->id],
            ['course_offering_id' => $elective->id],
        ],
    ])->assertRedirect(route('dashboard.section', ['section' => 'course-registration']));

    $registration = CourseRegistration::query()->with('items')->firstOrFail();
    expect($registration->status)->toBe(CourseRegistration::StatusSubmitted)
        ->and($registration->academic_session_semester_id)->toBe($semester->id)
        ->and($registration->items)->toHaveCount(2)
        ->and($registration->items->pluck('is_elective')->all())->toBe([false, true]);

    $this->actingAs($advisor->user)
        ->get(route('dashboard.course-registration.show', $registration->reviewRouteParameters()))
        ->assertOk();
    $this->patch(route('dashboard.course-registration.return', $registration->reviewRouteParameters()), [
        'feedback_note' => 'Reduce the selected course load.',
    ])->assertRedirect(route('dashboard.course-registration.show', $registration->reviewRouteParameters()));

    expect($registration->refresh()->status)->toBe(CourseRegistration::StatusReturned)
        ->and($registration->feedback_note)->toBe('Reduce the selected course load.');

    $this->actingAs($student->user)->post(route('dashboard.course-registration.store'), [
        'action' => 'submit',
        'items' => [['course_offering_id' => $normal->id]],
    ])->assertSessionHasNoErrors();
    $this->actingAs($advisor->user)
        ->patch(route('dashboard.course-registration.approve', $registration->reviewRouteParameters()))
        ->assertRedirect(route('dashboard.course-registration.show', $registration->reviewRouteParameters()));

    expect($registration->refresh()->status)->toBe(CourseRegistration::StatusApproved)
        ->and($registration->items)->toHaveCount(1);
});

test('registration accepts only courses in the open semester and enforces its unit limit', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
    ]);
    $session = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => AcademicYear::query()->where('year_number', 1)->firstOrFail()->id,
    ]);
    $semester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
        'maximum_course_units' => 4,
        'registration_enabled' => true,
    ]);
    $first = registrationOffering($set, $semester, 'CPE 101', false, 3);
    $second = registrationOffering($set, $semester, 'CPE 103', false, 2);

    $this->actingAs($student->user)->post(route('dashboard.course-registration.store'), [
        'action' => 'submit',
        'items' => [
            ['course_offering_id' => $first->id],
            ['course_offering_id' => $second->id],
        ],
    ])->assertSessionHasErrors('items');

    expect(CourseRegistration::query()->exists())->toBeFalse();
});

function registrationOffering(
    StudentSet $set,
    AcademicSessionSemester $semester,
    string $code,
    bool $isElective,
    int $units,
): CourseOffering {
    $course = Course::factory()->create([
        'student_set_id' => $set->id,
        'code' => $code,
        'title' => "{$code} Course",
        'slug' => str($code)->slug()->toString(),
    ]);

    return CourseOffering::factory()->create([
        'course_id' => $course->id,
        'academic_session_semester_id' => $semester->id,
        'course_title' => $course->title,
        'credit_units' => $units,
        'is_elective' => $isElective,
    ]);
}
