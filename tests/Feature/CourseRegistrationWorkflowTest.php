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
        'session' => $session->routeYears(),
        'semester' => 'first',
        'items' => [
            ['course_offering_id' => $normal->id],
            ['course_offering_id' => $elective->id],
        ],
    ])->assertRedirect(route('dashboard.section', ['section' => 'course-registration', 'session' => $session->routeYears(), 'semester' => 'first']));

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
        'session' => $session->routeYears(),
        'semester' => 'first',
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
        'session' => $session->routeYears(),
        'semester' => 'first',
        'items' => [
            ['course_offering_id' => $first->id],
            ['course_offering_id' => $second->id],
        ],
    ])->assertSessionHasErrors('items');

    expect(CourseRegistration::query()->exists())->toBeFalse();
});

test('a student can clear a saved draft without submitting an empty registration', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
    ]);
    $session = AcademicSession::factory()->create(['student_set_id' => $set->id]);
    $semester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
        'registration_enabled' => true,
    ]);
    $offering = registrationOffering($set, $semester, 'CPE 101', false, 3);
    $route = route('dashboard.course-registration.store');
    $period = ['session' => $session->routeYears(), 'semester' => 'first'];

    $this->actingAs($student->user)->post($route, $period + [
        'action' => 'save',
        'items' => [['course_offering_id' => $offering->id]],
    ])->assertSessionHasNoErrors();
    $this->post($route, $period + ['action' => 'save', 'items' => []])->assertSessionHasNoErrors();
    $this->post($route, $period + ['action' => 'submit', 'items' => []])->assertSessionHasErrors('items');

    $registration = CourseRegistration::query()->firstOrFail();
    expect($registration->status)->toBe(CourseRegistration::StatusDraft)
        ->and($registration->items()->count())->toBe(0);
});

test('prerequisites require an earlier published passing result and are checked again at approval', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
    ]);
    $first = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => AcademicYear::query()->where('year_number', 1)->firstOrFail()->id,
        'start_year' => 2024,
        'end_year' => 2025,
    ]);
    $second = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => AcademicYear::query()->where('year_number', 2)->firstOrFail()->id,
        'start_year' => 2025,
        'end_year' => 2026,
    ]);
    $firstSemesterId = Semester::query()->where('slug', 'first')->firstOrFail()->id;
    $previousSemester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $first->id,
        'semester_id' => $firstSemesterId,
    ]);
    $currentSemester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $second->id,
        'semester_id' => $firstSemesterId,
        'registration_enabled' => true,
    ]);
    $previousOffering = registrationOffering($set, $previousSemester, 'CPE 101', false, 3);
    $currentOffering = registrationOffering($set, $currentSemester, 'CPE 201', false, 3);
    $currentOffering->prerequisiteCourses()->attach($previousOffering->course_id);
    $previousRegistration = CourseRegistration::factory()->create([
        'student_profile_id' => $student->id,
        'academic_session_semester_id' => $previousSemester->id,
        'status' => CourseRegistration::StatusApproved,
    ]);
    $previousResult = $previousRegistration->items()->create([
        'course_offering_id' => $previousOffering->id,
        'course_code' => 'CPE 101',
        'course_title' => $previousOffering->course_title,
        'credit_units' => 3,
        'is_elective' => false,
        'grade' => 'B',
    ]);
    $payload = [
        'action' => 'submit',
        'session' => $second->routeYears(),
        'semester' => 'first',
        'items' => [['course_offering_id' => $currentOffering->id]],
    ];
    $registrationPage = route('dashboard.section', [
        'section' => 'course-registration',
        'session' => $second->routeYears(),
        'semester' => 'first',
    ]);

    $this->actingAs($student->user)->get($registrationPage)->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.studentCourseRegistration.hasAvailableCourses', true)
            ->where('dashboard.studentCourseRegistration.courseGroups.0.courses.0.missingPrerequisites', ['CPE 101']));
    $this->actingAs($student->user)->post(route('dashboard.course-registration.store'), $payload)
        ->assertSessionHasErrors('items');
    $previousOffering->forceFill(['results_published_at' => now()])->save();
    $previousResult->update(['grade' => 'F']);
    $this->post(route('dashboard.course-registration.store'), $payload)->assertSessionHasErrors('items');
    $previousResult->update(['grade' => 'B']);
    $this->get($registrationPage)->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.studentCourseRegistration.courseGroups.0.courses.0.missingPrerequisites', []));
    $this->post(route('dashboard.course-registration.store'), $payload)->assertSessionHasNoErrors();

    $registration = CourseRegistration::query()
        ->where('student_profile_id', $student->id)
        ->where('academic_session_semester_id', $currentSemester->id)
        ->firstOrFail();
    $previousResult->update(['grade' => 'F']);
    $this->actingAs($advisor->user)->patch(route('dashboard.course-registration.approve', $registration->reviewRouteParameters()))
        ->assertSessionHasErrors('items');
    expect($registration->fresh()->status)->toBe(CourseRegistration::StatusSubmitted);
    $previousResult->update(['grade' => 'B']);
    $this->patch(route('dashboard.course-registration.approve', $registration->reviewRouteParameters()))
        ->assertSessionHasNoErrors();
    expect($registration->fresh()->status)->toBe(CourseRegistration::StatusApproved);
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
    ]);

    return CourseOffering::factory()->create([
        'course_id' => $course->id,
        'academic_session_semester_id' => $semester->id,
        'course_title' => $course->title,
        'credit_units' => $units,
        'is_elective' => $isElective,
    ]);
}
