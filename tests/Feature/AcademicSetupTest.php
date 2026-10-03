<?php

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Due;
use App\Models\Semester;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\AcademicContextService;
use Database\Seeders\AcademicStructureSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an adviser can open an empty student sets page', function () {
    $advisor = AdvisorProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->get(route('dashboard.section', ['section' => 'student-sets']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserStudentSets.isCreating', false));
});

test('set progression uses a configured session and is shared by its students', function () {
    $this->seed(AcademicStructureSeeder::class);
    $set = StudentSet::factory()->create();
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
    ]);
    $firstYear = AcademicYear::query()->where('year_number', 1)->firstOrFail();
    $secondYear = AcademicYear::query()->where('year_number', 2)->firstOrFail();
    $secondSession = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $secondYear->id,
        'start_year' => $set->entry_start_year + 1,
        'end_year' => $set->entry_end_year + 1,
    ]);
    $firstSemester = Semester::query()->where('slug', 'first')->firstOrFail();
    AcademicSessionSemester::factory()->create([
        'academic_session_id' => $secondSession->id,
        'semester_id' => $firstSemester->id,
    ]);
    $sessionsBefore = AcademicSession::count();
    $context = app(AcademicContextService::class);
    expect($context->academicYearForStudent($student)->id)->toBe($firstYear->id);

    $this->actingAs($set->advisorProfile->user)
        ->get(route('student-sets.edit', $set->routeParameters()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.studentSetSettings.set.currentLevel.id', $firstYear->id));
    $this->patch(route('student-sets.update', $set->routeParameters()), [
        'student_signup_enabled' => false,
        'current_academic_year_id' => $secondYear->id,
        'current_semester_id' => $firstSemester->id,
    ])->assertRedirect(route('student-sets.edit', $set->routeParameters()));

    expect($context->academicYearForStudent($student->fresh())->id)->toBe($secondYear->id)
        ->and($set->refresh()->student_signup_enabled)->toBeFalse()
        ->and(AcademicSession::count())->toBe($sessionsBefore);
    $this->get(route('student-sets.show', $set->routeParameters()))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserStudents.students.0.currentLevel.id', $secondYear->id));
});

test('set settings reject other advisers students and invalid academic years', function () {
    $this->seed(AcademicStructureSeeder::class);
    $set = StudentSet::factory()->create();
    $year = $set->current_academic_year_id;
    $payload = [
        'student_signup_enabled' => false,
        'current_academic_year_id' => $year,
        'current_semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
    ];
    $other = AdvisorProfile::factory()->create();
    $this->actingAs($other->user)->get(route('student-sets.edit', $set->routeParameters()))->assertNotFound();
    $this->patch(route('student-sets.update', $set->routeParameters()), $payload)->assertNotFound();
    $student = StudentProfile::factory()->create();
    $this->actingAs($student->user)->patch(route('student-sets.update', $set->routeParameters()), $payload)->assertForbidden();
    $this->actingAs($set->advisorProfile->user)->patch(route('student-sets.update', $set->routeParameters()), [
        ...$payload,
        'current_academic_year_id' => 999999,
    ])->assertSessionHasErrors('current_academic_year_id');
    $unconfiguredYear = AcademicYear::query()
        ->whereKeyNot($year)
        ->firstOrFail();
    $this->patch(route('student-sets.update', $set->routeParameters()), [
        ...$payload,
        'current_academic_year_id' => $unconfiguredYear->id,
    ])->assertSessionHasErrors('current_academic_year_id');
    expect($set->refresh()->current_academic_year_id)->toBe($year)
        ->and($set->student_signup_enabled)->toBeTrue();
});

test('a set keeps a current semester after its first session is created', function () {
    $this->seed(AcademicStructureSeeder::class);
    $set = StudentSet::factory()->create([
        'current_academic_year_id' => null,
        'current_semester_id' => null,
    ]);
    $firstYear = AcademicYear::query()->where('year_number', 1)->firstOrFail();
    $secondYear = AcademicYear::query()->where('year_number', 2)->firstOrFail();
    $firstSemester = Semester::query()->where('slug', 'first')->firstOrFail();
    $this->actingAs($set->advisorProfile->user);

    foreach ([$firstYear, $secondYear] as $index => $year) {
        $this->post(route('student-sets.sessions.store', $set->routeParameters()), [
            'academic_year_id' => $year->id,
            'start_year' => $set->entry_start_year + $index,
            'first_semester_maximum_course_units' => 24,
            'second_semester_maximum_course_units' => 24,
        ])->assertSessionHasNoErrors();
    }

    expect($set->refresh()->current_academic_year_id)->toBe($firstYear->id)
        ->and($set->current_semester_id)->toBe($firstSemester->id);

    $this->patch(route('student-sets.update', $set->routeParameters()), [
        'current_academic_year_id' => null,
        'current_semester_id' => null,
    ])->assertSessionHasErrors('current_academic_year_id');
    $this->patch(route('student-sets.update', $set->routeParameters()), [
        'current_academic_year_id' => $firstYear->id,
        'current_semester_id' => null,
    ])->assertSessionHasErrors('current_semester_id');

    expect($set->refresh()->current_academic_year_id)->toBe($firstYear->id)
        ->and($set->current_semester_id)->toBe($firstSemester->id);
});

test('the chosen current semester remains visible to students until the adviser changes it', function () {
    $this->seed(AcademicStructureSeeder::class);
    $set = StudentSet::factory()->create();
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
    ]);
    $session = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $set->current_academic_year_id,
        'start_year' => $set->entry_start_year,
        'end_year' => $set->entry_end_year,
    ]);
    $first = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
    ]);
    $second = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'second')->firstOrFail()->id,
    ]);

    $this->actingAs($set->advisorProfile->user)
        ->patch(route('student-sets.update', $set->routeParameters()), [
            'current_academic_year_id' => $session->academic_year_id,
            'current_semester_id' => $first->semester_id,
        ])->assertSessionHasNoErrors();
    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.studentHome.currentSemester.name', '1st Semester')
            ->where('dashboard.studentHome.courses', []));
    $this->get(route('dashboard.section', [
        'section' => 'courses',
        'session' => $session->routeYears(),
        'semester' => 'first',
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentCourses.selectedCourses', [])
        ->where('dashboard.studentCourses.selectedSemesterId', $first->id));

    $this->actingAs($set->advisorProfile->user)
        ->patch(route('student-sets.update', $set->routeParameters()), [
            'current_academic_year_id' => $session->academic_year_id,
            'current_semester_id' => $second->semester_id,
        ])->assertSessionHasNoErrors();
    expect($set->refresh()->current_semester_id)->toBe($second->semester_id);
});

test('an adviser creates a normalized student set from its department and entry year', function () {
    $advisor = AdvisorProfile::factory()->create();

    $response = $this->actingAs($advisor->user)->post(route('student-sets.store'), [
        'department_name' => '  COMPUTER   engineering ',
        'start_year' => 2025,
    ]);

    $studentSet = StudentSet::query()->firstOrFail();

    $response->assertRedirect(route('student-sets.show', $studentSet->routeParameters()));
    expect($studentSet->advisor_profile_id)->toBe($advisor->id)
        ->and($studentSet->label())->toBe('2025/2026 Computer Engineering')
        ->and($studentSet->entry_end_year)->toBe(2026)
        ->and($studentSet->department_slug)->toBe('computer-engineering')
        ->and(AcademicSession::query()->where('student_set_id', $studentSet->id)->exists())->toBeFalse();

    $this->actingAs($advisor->user)->post(route('student-sets.store'), [
        'department_name' => 'Computer Engineering',
        'start_year' => 2025,
    ])->assertSessionHasErrors('start_year');
});

test('an adviser can add a set with a department logo', function () {
    Storage::fake('local');
    $advisor = AdvisorProfile::factory()->create();
    $details = ['department_name' => 'Computer Engineering', 'start_year' => 2025];

    $this->actingAs($advisor->user)->post(route('student-sets.store'), [
        ...$details,
        'logo' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
    ])->assertSessionHasErrors('logo');
    expect(StudentSet::query()->count())->toBe(0);

    $this->post(route('student-sets.store'), [
        ...$details,
        'logo' => UploadedFile::fake()->image('department.png', 160, 160),
    ])->assertSessionHasNoErrors();

    $set = StudentSet::query()->firstOrFail();
    expect(Storage::disk('local')->exists($set->logo_path))->toBeTrue();
    $this->get(route('student-sets.logo.show', $set->routeParameters()))->assertOk();
});

test('a student set page is owned by its adviser and lists only that sets students', function () {
    $advisor = AdvisorProfile::factory()->create();
    $studentSet = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $otherSet = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $user = User::factory()->student()->create(['name' => 'Adaeze Okoro']);
    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'official_name' => 'Adaeze Okoro',
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $studentSet->id,
    ]);
    StudentProfile::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $otherSet->id,
    ]);

    $this->actingAs($advisor->user)
        ->get(route('student-sets.show', $studentSet->routeParameters()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserStudents.totalStudents', 1)
            ->where('dashboard.adviserStudents.students.0.fullName', 'Adaeze Okoro'));

    $otherAdvisor = AdvisorProfile::factory()->create();
    $this->actingAs($otherAdvisor->user)
        ->get(route('student-sets.show', $studentSet->routeParameters()))
        ->assertNotFound();
});

test('an adviser cannot lower a semester limit below an existing course registration', function () {
    $this->seed(AcademicStructureSeeder::class);
    $set = StudentSet::factory()->create();
    $session = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $set->current_academic_year_id,
        'start_year' => $set->entry_start_year,
        'end_year' => $set->entry_end_year,
    ]);
    $firstSemester = AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
        'maximum_course_units' => 24,
    ]);
    AcademicSessionSemester::factory()->create([
        'academic_session_id' => $session->id,
        'semester_id' => Semester::query()->where('slug', 'second')->firstOrFail()->id,
        'maximum_course_units' => 24,
    ]);
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
    ]);
    $registration = CourseRegistration::factory()->create([
        'student_profile_id' => $student->id,
        'academic_session_semester_id' => $firstSemester->id,
    ]);
    CourseRegistrationItem::factory()->create([
        'course_registration_id' => $registration->id,
        'credit_units' => 3,
    ]);

    $this->actingAs($set->advisorProfile->user)
        ->patch(route('student-sets.sessions.update', [
            ...$set->routeParameters(),
            'sessionYears' => $session->routeYears(),
        ]), [
            'academic_year_id' => $session->academic_year_id,
            'start_year' => $session->start_year,
            'first_semester_maximum_course_units' => 2,
            'second_semester_maximum_course_units' => 24,
        ])->assertSessionHasErrors('first_semester_maximum_course_units');

    expect($firstSemester->refresh()->maximum_course_units)->toBe(24);
});

test('an adviser cannot remove a session containing dues', function () {
    $this->seed(AcademicStructureSeeder::class);
    $set = StudentSet::factory()->create();
    $session = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'start_year' => $set->entry_start_year + 1,
        'end_year' => $set->entry_end_year + 1,
        'academic_year_id' => AcademicYear::query()->where('year_number', 2)->firstOrFail()->id,
    ]);
    Due::factory()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'academic_session_id' => $session->id,
    ]);

    $this->actingAs($set->advisorProfile->user)
        ->delete(route('student-sets.sessions.destroy', [
            ...$set->routeParameters(),
            'sessionYears' => $session->routeYears(),
        ]))
        ->assertSessionHas('error');

    expect($session->fresh())->not->toBeNull();
});
