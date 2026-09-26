<?php

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\AcademicContextService;
use Database\Seeders\AcademicStructureSeeder;
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
    AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $secondYear->id,
        'start_year' => $set->entry_start_year + 1,
        'end_year' => $set->entry_end_year + 1,
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
    $payload = ['student_signup_enabled' => false, 'current_academic_year_id' => $year];
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

test('a student set page is owned by its adviser and lists only that sets students', function () {
    $advisor = AdvisorProfile::factory()->create();
    $studentSet = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $otherSet = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $user = User::factory()->student()->create(['name' => 'Adaeze Okoro']);
    StudentProfile::factory()->create([
        'user_id' => $user->id,
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
