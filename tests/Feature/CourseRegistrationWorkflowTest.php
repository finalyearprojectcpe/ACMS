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

test('only the adviser for a set can assign courses to its students', function () {
    [$advisor, $set, $student, $session, $period] = assignmentFixture();
    $otherAdviser = AdvisorProfile::factory()->create();
    $offering = assignmentOffering($set, $period, 'CPE 101', 3);
    $parameters = assignmentParameters($set, $student, $session);
    $page = route('student-sets.assignments.show', $parameters);
    $save = route('student-sets.assignments.store', $parameters);

    $this->actingAs($otherAdviser->user)->get($page)->assertNotFound();
    $this->patch($save, ['action' => 'assign', 'items' => [['course_offering_id' => $offering->id]]])->assertNotFound();
    $this->actingAs($student->user)->get($page)->assertForbidden();
    $this->patch($save, ['action' => 'assign', 'items' => [['course_offering_id' => $offering->id]]])->assertForbidden();

    $this->actingAs($advisor->user)->get($page)->assertOk()->assertInertia(fn (Assert $response) => $response
        ->where('dashboard.adviserCourseAssignment.student.registrationNumber', $student->registration_number));
    expect(CourseRegistration::query()->exists())->toBeFalse();
});

test('the adviser assigns normal and elective courses and the student can only view them', function () {
    [$advisor, $set, $student, $session, $period] = assignmentFixture(5);
    $normal = assignmentOffering($set, $period, 'CPE 101', 3);
    $elective = assignmentOffering($set, $period, 'CPE 103', 2, true);
    $save = route('student-sets.assignments.store', assignmentParameters($set, $student, $session));

    $this->actingAs($advisor->user)->patch($save, [
        'action' => 'assign',
        'items' => [['course_offering_id' => $normal->id], ['course_offering_id' => $elective->id]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('student-sets.courses.show', [
        ...$set->routeParameters(),
        'session' => $session->routeYears(),
        'semester' => 'first',
    ]));

    $this->patch($save, [
        'action' => 'assign',
        'from' => 'home',
        'items' => [['course_offering_id' => $normal->id], ['course_offering_id' => $elective->id]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    $registration = CourseRegistration::query()->with('items')->firstOrFail();
    expect($registration->status)->toBe(CourseRegistration::StatusAssigned)
        ->and($registration->assigned_by_user_id)->toBe($advisor->user_id)
        ->and($registration->items)->toHaveCount(2)
        ->and($registration->items->pluck('is_elective')->all())->toBe([false, true]);

    $this->actingAs($student->user)->get(route('dashboard.section', [
        'section' => 'courses', 'session' => $session->routeYears(), 'semester' => 'first',
    ]))->assertOk()->assertInertia(fn (Assert $response) => $response
        ->has('dashboard.studentCourses.selectedCourses', 2)
        ->where('dashboard.studentCourses.selectedCourses.1.isElective', true));
});

test('an adviser can open an unclaimed student profile and assign courses from it', function () {
    [$advisor, $set, $student, $session, $period] = assignmentFixture();
    $student->forceFill(['user_id' => null])->save();
    $offering = assignmentOffering($set, $period, 'CPE 101', 3);
    $profile = route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]);
    $assignment = route('student-sets.assignments.show', [
        ...assignmentParameters($set, $student, $session),
        'from' => 'profile',
    ]);

    $this->actingAs($advisor->user)->get($profile)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentRecord.courseAssignments.0.status', 'unassigned')
        ->where('dashboard.studentRecord.courseAssignments.0.url', $assignment));

    $this->patch(route('student-sets.assignments.store', assignmentParameters($set, $student, $session)), [
        'action' => 'assign',
        'from' => 'profile',
        'items' => [['course_offering_id' => $offering->id]],
    ])->assertSessionHasNoErrors()->assertRedirect($profile);

    $this->get($profile)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentRecord.courseAssignments.0.status', 'assigned')
        ->where('dashboard.studentRecord.courseAssignments.0.courseCount', 1)
        ->where('dashboard.studentRecord.courseAssignments.0.units', 3));

    $this->actingAs(AdvisorProfile::factory()->create()->user)->get($profile)->assertNotFound();
});

test('an assignment cannot exceed the semester limit or include another semesters course', function () {
    [$advisor, $set, $student, $session, $period] = assignmentFixture(4);
    $first = assignmentOffering($set, $period, 'CPE 101', 3);
    $second = assignmentOffering($set, $period, 'CPE 103', 2);
    $otherPeriod = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'second')->firstOrFail()->id,
    ]);
    $other = assignmentOffering($set, $otherPeriod, 'CPE 102', 2);
    $save = route('student-sets.assignments.store', assignmentParameters($set, $student, $session));

    $this->actingAs($advisor->user)->patch($save, [
        'action' => 'assign',
        'items' => [['course_offering_id' => $first->id], ['course_offering_id' => $second->id]],
    ])->assertSessionHasErrors('items');
    $this->patch($save, [
        'action' => 'assign',
        'items' => [['course_offering_id' => $other->id]],
    ])->assertSessionHasErrors('items');
    expect(CourseRegistration::query()->exists())->toBeFalse();
});

test('prerequisites require a passing published result before assignment', function () {
    [$advisor, $set, $student, $session, $period] = assignmentFixture();
    $nextYear = AcademicYear::query()->where('year_number', 2)->firstOrFail();
    $nextSession = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $nextYear->id,
        'start_year' => $session->start_year + 1,
        'end_year' => $session->end_year + 1,
    ]);
    $nextPeriod = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $nextSession->id,
        'semester_id' => $period->semester_id,
    ]);
    $previous = assignmentOffering($set, $period, 'CPE 101', 3);
    $current = assignmentOffering($set, $nextPeriod, 'CPE 201', 3);
    $current->prerequisiteCourses()->attach($previous->course_id);
    $earlier = CourseRegistration::factory()->create([
        'student_profile_id' => $student->id,
        'academic_session_semester_id' => $period->id,
        'status' => CourseRegistration::StatusAssigned,
    ]);
    $result = $earlier->items()->create([
        'course_offering_id' => $previous->id,
        'course_code' => 'CPE 101',
        'course_title' => $previous->course_title,
        'credit_units' => 3,
        'is_elective' => false,
        'grade' => 'B',
    ]);
    $save = route('student-sets.assignments.store', assignmentParameters($set, $student, $nextSession));
    $payload = ['action' => 'assign', 'items' => [['course_offering_id' => $current->id]]];

    $this->actingAs($advisor->user)->patch($save, $payload)->assertSessionHasErrors('items');
    $previous->forceFill(['results_published_at' => now()])->save();
    $result->update(['grade' => 'F']);
    $this->patch($save, $payload)->assertSessionHasErrors('items');
    $result->update(['grade' => 'B']);
    $this->patch($save, $payload)->assertSessionHasNoErrors();
    expect(CourseRegistration::query()->where('academic_session_semester_id', $nextPeriod->id)->firstOrFail()->status)
        ->toBe(CourseRegistration::StatusAssigned);
});

test('a failed course can be assigned as a carryover in a later semester and appears in that semesters results', function () {
    [$advisor, $set, $student, $session, $period] = assignmentFixture();
    $previous = assignmentOffering($set, $period, 'CPE 101', 3);
    $previous->forceFill(['results_published_at' => now()])->save();
    $earlier = CourseRegistration::factory()->create([
        'student_profile_id' => $student->id,
        'academic_session_semester_id' => $period->id,
        'status' => CourseRegistration::StatusAssigned,
    ]);
    $earlier->items()->create([
        'course_offering_id' => $previous->id,
        'course_code' => $previous->course->code,
        'course_title' => $previous->course_title,
        'credit_units' => 3,
        'is_elective' => false,
        'grade' => 'F',
    ]);
    $nextYear = AcademicYear::query()->where('year_number', 2)->firstOrFail();
    $nextSession = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $nextYear->id,
        'start_year' => $session->start_year + 1,
        'end_year' => $session->end_year + 1,
    ]);
    $nextPeriod = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $nextSession->id,
        'semester_id' => $period->semester_id,
    ]);
    $parameters = assignmentParameters($set, $student, $nextSession);

    $this->actingAs($advisor->user)->get(route('student-sets.assignments.show', $parameters))
        ->assertOk()->assertInertia(fn (Assert $response) => $response
        ->where('dashboard.adviserCourseAssignment.courseGroups.0.key', 'carryover'));
    $this->patch(route('student-sets.assignments.store', $parameters), [
        'action' => 'assign',
        'items' => [['course_offering_id' => $previous->id]],
    ])->assertSessionHasNoErrors();

    $retake = CourseOffering::query()->where('academic_session_semester_id', $nextPeriod->id)
        ->where('course_id', $previous->course_id)->firstOrFail();
    $item = CourseRegistration::query()->where('academic_session_semester_id', $nextPeriod->id)
        ->firstOrFail()->items()->firstOrFail();
    expect($retake->is_retake_only)->toBeTrue()
        ->and($item->course_offering_id)->toBe($retake->id)
        ->and($item->assignment_origin)->toBe('carryover');

    $this->get(route('student-sets.matrix.show', [
        ...$set->routeParameters(),
        'session' => $nextSession->routeYears(),
        'semester' => 'first',
    ]))->assertOk()->assertInertia(fn (Assert $response) => $response
        ->has('dashboard.adviserSetResults.columns', 1)
        ->where('dashboard.adviserSetResults.columns.0.code', 'CPE 101')
        ->where('dashboard.adviserSetResults.columns.0.carryoverOnly', true)
        ->where("dashboard.adviserSetResults.students.0.cells.{$retake->id}.assignmentOrigin", 'carryover'));

    $deferredStudent = StudentProfile::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
    ]);
    $this->patch(route('student-sets.assignments.store', assignmentParameters($set, $deferredStudent, $nextSession)), [
        'action' => 'assign',
        'items' => [['course_offering_id' => $retake->id]],
    ])->assertSessionHasNoErrors();

    $this->get(route('student-sets.matrix.show', [
        ...$set->routeParameters(),
        'session' => $nextSession->routeYears(),
        'semester' => 'first',
    ]))->assertOk()->assertInertia(fn (Assert $response) => $response
        ->where('dashboard.adviserSetResults.columns.0.carryoverOnly', false));
    expect(CourseRegistration::query()->where('student_profile_id', $deferredStudent->id)
        ->firstOrFail()->items()->firstOrFail()->assignment_origin)->toBe('deferred');
});

/** @return array{AdvisorProfile, StudentSet, StudentProfile, AcademicSession, AcademicSessionSemester} */
function assignmentFixture(int $limit = 24): array
{
    app(AcademicStructureSeeder::class)->run();
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
    $period = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
        'maximum_course_units' => $limit,
    ]);

    return [$advisor, $set, $student, $session, $period];
}

function assignmentOffering(StudentSet $set, AcademicSessionSemester $period, string $code, int $units, bool $elective = false): CourseOffering
{
    $course = Course::factory()->create(['student_set_id' => $set->id, 'code' => $code, 'credit_units' => $units, 'is_elective' => $elective]);

    return CourseOffering::factory()->create([
        'course_id' => $course->id,
        'academic_session_semester_id' => $period->id,
        'credit_units' => $units,
        'is_elective' => $elective,
    ]);
}

function assignmentParameters(StudentSet $set, StudentProfile $student, AcademicSession $session): array
{
    return [...$set->routeParameters(), 'sessionYears' => $session->routeYears(), 'semester' => 'first', 'registrationNumber' => $student->routeRegistrationNumber()];
}
