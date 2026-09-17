<?php

use App\AccountStatus;
use App\Models\AcademicSession;
use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an adviser adds a student to an owned set', function () {
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);

    $this->actingAs($advisor->user)->post(route('dashboard.students.store'), [
        'student_set_id' => $set->id,
        'registration_number' => '2025001234',
        'access_code' => '',
    ])->assertRedirect(route('dashboard.section', ['section' => 'students']));

    $student = StudentProfile::query()->where('registration_number', '2025001234')->firstOrFail();

    expect($student->advisor_profile_id)->toBe($advisor->id)
        ->and($student->student_set_id)->toBe($set->id)
        ->and($student->user->status)->toBe(AccountStatus::Pending)
        ->and($student->user->access_code)->toHaveLength(8);
});

test('an adviser cannot add a student to another advisers set', function () {
    $advisor = AdvisorProfile::factory()->create();
    $otherSet = StudentSet::factory()->create();

    $this->actingAs($advisor->user)->post(route('dashboard.students.store'), [
        'student_set_id' => $otherSet->id,
        'registration_number' => '2025001234',
    ])->assertSessionHasErrors('student_set_id');

    expect($advisor->students()->count())->toBe(0);
});

test('spreadsheet batches provision students with their supplied or generated access codes', function () {
    $advisor = AdvisorProfile::factory()->create();
    $session = AcademicSession::factory()->create(['advisor_profile_id' => $advisor->id]);
    $set = StudentSet::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'entry_academic_session_id' => $session->id,
    ]);

    $this->actingAs($advisor->user)->post(route('dashboard.students.import.store'), [
        'student_set_id' => $set->id,
        'file_name' => 'students.xlsx',
        'import_key' => 'student-import-test',
        'rows' => [
            ['registrationNumber' => '2025000001', 'accessCode' => 'FIRST-CODE'],
            ['registrationNumber' => '2025000002', 'accessCode' => ''],
        ],
    ])->assertRedirect(route('dashboard.section', ['section' => 'students']));

    $students = $advisor->students()->with('user')->orderBy('registration_number')->get();

    expect($students)->toHaveCount(2)
        ->and($students[0]->user->access_code)->toBe('FIRST-CODE')
        ->and($students[1]->user->access_code)->toHaveLength(8);
});

test('the student list is scoped to the adviser and links to registration-number profiles', function () {
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $user = User::factory()->student()->create(['name' => 'Adaeze Okoro']);
    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
        'registration_number' => '2025001234',
    ]);
    StudentProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->get(route('dashboard.section', ['section' => 'students']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.adviserStudents.students', 1)
            ->where('dashboard.adviserStudents.students.0.fullName', 'Adaeze Okoro')
            ->where(
                'dashboard.adviserStudents.students.0.profileUrl',
                route('dashboard.students.show', ['registrationNumber' => '2025001234']),
            ));
});
