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

test('subjects release separately while incomplete semesters keep GPA pending and corrections are recorded', function () {
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

    $offerings = $period->courseOfferings()->with('course')->orderBy('id')->get();
    $first = $offerings->firstOrFail();
    $firstRoute = [...$parameters, 'courseCode' => $first->course->urlCode()];

    $this->actingAs($adviser)->post(route('student-sets.results.publish', $firstRoute))
        ->assertSessionHasErrors('results');

    $period->update(['registration_enabled' => false]);
    CourseRegistration::query()->where('academic_session_semester_id', $period->id)
        ->where('status', CourseRegistration::StatusSubmitted)
        ->update(['status' => CourseRegistration::StatusReturned]);

    foreach ($offerings as $offering) {
        $items = CourseRegistrationItem::query()->where('course_offering_id', $offering->id)
            ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusApproved))
            ->get();
        $courseRoute = [...$parameters, 'courseCode' => $offering->course->urlCode()];
        $this->patch(route('student-sets.results.course.save', $courseRoute), [
            'grades' => $items->mapWithKeys(fn ($item) => [(string) $item->id => 'A'])->all(),
        ])->assertSessionHasNoErrors();
    }

    $this->post(route('student-sets.results.publish', $firstRoute))->assertSessionHasNoErrors();
    expect($first->refresh()->results_published_at)->not->toBeNull();

    $this->actingAs($student)->get(route('dashboard.section', ['section' => 'results']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('dashboard.studentResults.periods', 5)
        ->has('dashboard.studentResults.history', 5)
        ->where('dashboard.studentResults.selected', null)
        ->where('dashboard.studentResults.history.4.gpa', null));
    $this->get(route('dashboard.section', [
        'section' => 'results', 'session' => '20262027', 'semester' => 'first',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.gpa', null)
        ->where('dashboard.studentResults.selected.courses.0.grade', 'A')
        ->where('dashboard.studentResults.selected.courses.1.grade', null)
        ->where('dashboard.studentResults.selected.courses.1.pending', true));

    $submitted = CourseRegistration::query()->where('academic_session_semester_id', $period->id)
        ->where('status', CourseRegistration::StatusReturned)->firstOrFail();
    $submitted->update(['status' => CourseRegistration::StatusSubmitted]);
    $this->actingAs($adviser)->patch(route('dashboard.course-registration.approve', $submitted->reviewRouteParameters()))
        ->assertSessionHasErrors('registration');
    $submitted->update(['status' => CourseRegistration::StatusReturned]);

    foreach ($offerings->skip(1) as $offering) {
        $this->actingAs($adviser)->post(route('student-sets.results.publish', [
            ...$parameters, 'courseCode' => $offering->course->urlCode(),
        ]))->assertSessionHasNoErrors();
    }

    $this->actingAs($student)->get(route('dashboard.section', ['section' => 'results']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.history.4.gpa', 5));

    $item = CourseRegistrationItem::query()->where('course_offering_id', $first->id)
        ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusApproved))
        ->firstOrFail();
    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', [
        ...$parameters, 'courseCode' => $first->course->urlCode(),
    ]), ['grades' => [(string) $item->id => 'B']])->assertSessionHasErrors('reason');
    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', [
        ...$parameters, 'courseCode' => $first->course->urlCode(),
    ]), ['grades' => [(string) $item->id => 'B'], 'reason' => 'Verified the score sheet.'])->assertSessionHasNoErrors();
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

    $previewUrl = route('dashboard.students.statement.preview', ['registrationNumber' => $student->registration_number]);
    $downloadUrl = route('dashboard.students.statement.download', ['registrationNumber' => $student->registration_number]);
    $this->get($previewUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentRecordStatement.student.registrationNumber', $student->registration_number)
        ->has('dashboard.studentRecordStatement.periods'));
    $download = $this->get($downloadUrl)->assertOk()->assertDownload('results-to-date-'.$student->registration_number.'.docx');
    $path = $download->baseResponse->getFile()->getPathname();
    $word = new ZipArchive;
    expect($word->open($path))->toBeTrue();
    expect($word->getFromName('word/document.xml'))->toContain('STUDENT RESULTS TO DATE');
    $word->close();
    unlink($path);

    $this->get(route('dashboard.students.results.semester', [
        'registrationNumber' => $student->registration_number,
        'sessionYears' => '20262027',
        'semester' => 'first',
    ]))->assertNotFound();

    $otherAdviser = AdvisorProfile::factory()->create();
    $this->actingAs($otherAdviser->user)->get($profileUrl)->assertNotFound();
    $this->get($resultUrl)->assertNotFound();
    $this->get($previewUrl)->assertNotFound();
    $this->get($downloadUrl)->assertNotFound();
});

test('a published missing script is visible without a GPA and its correction is audited', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu')->firstOrFail();
    $set = StudentSet::query()->firstOrFail();
    $period = AcademicSessionSemester::query()
        ->whereHas('academicSession', fn ($query) => $query->where('start_year', 2026))
        ->whereHas('semester', fn ($query) => $query->where('slug', 'first'))
        ->firstOrFail();
    $offering = $period->courseOfferings()->with('course')->orderBy('id')->firstOrFail();
    $items = CourseRegistrationItem::query()->where('course_offering_id', $offering->id)
        ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusApproved))
        ->get();
    $missingItem = $items->firstOrFail();
    $route = [
        ...$set->routeParameters(),
        'sessionYears' => '20262027',
        'semester' => 'first',
        'courseCode' => $offering->course->urlCode(),
    ];
    $grades = $items->mapWithKeys(fn ($item) => [(string) $item->id => $item->id === $missingItem->id ? 'missing_script' : 'A'])->all();

    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', $route), [
        'grades' => $grades,
    ])->assertSessionHasNoErrors();

    expect($missingItem->refresh()->grade)->toBeNull()
        ->and($missingItem->missing_script)->toBeTrue();

    $period->update(['registration_enabled' => false]);
    CourseRegistration::query()->where('academic_session_semester_id', $period->id)
        ->where('status', CourseRegistration::StatusSubmitted)
        ->update(['status' => CourseRegistration::StatusReturned]);

    $this->post(route('student-sets.results.publish', $route))->assertSessionHasNoErrors();

    $student = $missingItem->courseRegistration->studentProfile->user;
    $courseIndex = $missingItem->courseRegistration->items()->orderBy('course_code')->pluck('course_code')
        ->search($missingItem->course_code);
    $studentResults = route('dashboard.section', [
        'section' => 'results', 'session' => '20262027', 'semester' => 'first',
    ]);

    $this->actingAs($student)->get($studentResults)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.gpa', null)
        ->where("dashboard.studentResults.selected.courses.{$courseIndex}.missingScript", true)
        ->where("dashboard.studentResults.selected.courses.{$courseIndex}.grade", null));

    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', $route), [
        'grades' => array_replace($grades, [(string) $missingItem->id => 'A']),
    ])->assertSessionHasErrors('reason');
    $this->patch(route('student-sets.results.course.save', $route), [
        'grades' => array_replace($grades, [(string) $missingItem->id => 'A']),
        'reason' => 'The marked script was found and graded.',
    ])->assertSessionHasNoErrors();

    expect($missingItem->refresh()->grade)->toBe('A')
        ->and($missingItem->missing_script)->toBeFalse();
    expect(DB::table('grade_corrections')->where('course_registration_item_id', $missingItem->id)
        ->where('old_missing_script', true)->where('new_grade', 'A')->exists())->toBeTrue();
});
