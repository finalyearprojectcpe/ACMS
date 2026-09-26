<?php

use App\Models\AcademicSessionSemester;
use App\Models\AdvisorProfile;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\StudentSet;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('semester results are private until complete publication and grade corrections are recorded', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu')->firstOrFail();
    $student = User::query()->where('email', 'student3@futo.edu')->firstOrFail();
    $set = StudentSet::query()->firstOrFail();
    $period = AcademicSessionSemester::query()
        ->whereHas('academicSession', fn ($query) => $query->where('start_year', 2026))
        ->whereHas('semester', fn ($query) => $query->where('slug', 'first'))
        ->firstOrFail();
    $parameters = [...$set->routeParameters(), 'sessionYears' => '20262027', 'semester' => 'first'];

    $this->actingAs($student)->get(route('dashboard.section', ['section' => 'results']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('dashboard.studentResults.periods', 4));

    $this->actingAs($adviser)->post(route('student-sets.results.publish', $parameters))
        ->assertSessionHasErrors('results');

    $period->update(['registration_enabled' => false]);
    CourseRegistration::query()->where('academic_session_semester_id', $period->id)
        ->where('status', CourseRegistration::StatusSubmitted)
        ->update(['status' => CourseRegistration::StatusReturned]);

    $offerings = $period->courseOfferings()->get();
    foreach ($offerings as $offering) {
        $items = CourseRegistrationItem::query()->where('course_offering_id', $offering->id)
            ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusApproved))
            ->get();
        $courseRoute = [...$parameters, 'courseCode' => $offering->course->urlCode()];
        $this->patch(route('student-sets.results.course.save', $courseRoute), [
            'grades' => $items->mapWithKeys(fn ($item) => [(string) $item->id => 'A'])->all(),
        ])->assertSessionHasNoErrors();
    }

    $this->post(route('student-sets.results.publish', $parameters))->assertSessionHasNoErrors();
    expect($period->refresh()->results_published_at)->not->toBeNull();

    $this->actingAs($student)->get(route('dashboard.section', ['section' => 'results']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('dashboard.studentResults.periods', 5)
        ->where('dashboard.studentResults.selected.gpa', 5));

    $first = $offerings->first();
    $item = CourseRegistrationItem::query()->where('course_offering_id', $first->id)
        ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusApproved))
        ->firstOrFail();
    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', [
        ...$parameters, 'courseCode' => $first->course->urlCode(),
    ]), ['grades' => [(string) $item->id => 'B']])->assertSessionHasNoErrors();
    expect(DB::table('grade_corrections')->where('course_registration_item_id', $item->id)->exists())->toBeTrue();

    $otherAdviser = AdvisorProfile::factory()->create();
    $this->actingAs($otherAdviser->user)->get(route('student-sets.results.semester', $parameters))->assertNotFound();
    $this->get(route('student-sets.results.course', [
        ...$parameters, 'courseCode' => $first->course->urlCode(),
    ]))->assertNotFound();
});

test('student record shows a published GPA matrix and scoped semester detail', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu')->firstOrFail();
    $student = User::query()->where('email', 'student3@futo.edu')->firstOrFail()->studentProfile;
    $profileUrl = route('dashboard.students.show', ['registrationNumber' => $student->registration_number]);
    $resultUrl = route('dashboard.students.results.semester', [
        'registrationNumber' => $student->registration_number,
        'sessionYears' => '20242025',
        'semester' => 'first',
    ]);

    $this->actingAs($adviser)->get($profileUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentRecord.results.columns.0.level', '100 Level')
        ->where('dashboard.studentRecord.results.columns.0.semesters.0.url', $resultUrl)
        ->where('dashboard.studentRecord.results.columns.2.semesters.0.gpa', null)
        ->has('dashboard.studentRecord.dues.unpaid')
        ->has('dashboard.studentRecord.dues.paid'));
    $this->get($resultUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentRecordResults.student.registrationNumber', $student->registration_number)
        ->has('dashboard.studentRecordResults.results.selected.courses'));

    $this->get(route('dashboard.students.results.semester', [
        'registrationNumber' => $student->registration_number,
        'sessionYears' => '20262027',
        'semester' => 'first',
    ]))->assertNotFound();

    $otherAdviser = AdvisorProfile::factory()->create();
    $this->actingAs($otherAdviser->user)->get($profileUrl)->assertNotFound();
    $this->get($resultUrl)->assertNotFound();
});
