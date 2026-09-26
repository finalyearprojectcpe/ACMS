<?php

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('the adviser set page shows its signup link and joined students', function () {
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $studentUser = User::factory()->student()->create([
        'name' => 'Adaeze Okoro',
        'email' => 'adaeze@futo.edu',
    ]);
    StudentProfile::factory()->create([
        'user_id' => $studentUser->id,
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
        'registration_number' => '2025001234',
    ]);
    StudentProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->get(route('student-sets.show', $set->routeParameters()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserStudents.assignedSets.0.signupUrl', $set->signupUrl())
            ->where('dashboard.adviserStudents.assignedSets.0.signupEnabled', true)
            ->has('dashboard.adviserStudents.students', 1)
            ->where('dashboard.adviserStudents.students.0.fullName', 'Adaeze Okoro')
            ->where(
                'dashboard.adviserStudents.students.0.profileUrl',
                route('dashboard.students.show', ['registrationNumber' => '2025001234']),
            ));
});

test('an adviser can close reopen and replace their set signup link', function () {
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $this->seed(AcademicStructureSeeder::class);
    $year = AcademicYear::query()->where('year_number', 1)->firstOrFail();
    AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'academic_year_id' => $year->id,
    ]);
    $parameters = $set->routeParameters();
    $originalToken = $set->invite_token;

    $this->actingAs($advisor->user)
        ->patch(route('student-sets.update', $parameters), ['student_signup_enabled' => false, 'current_academic_year_id' => $year->id])
        ->assertRedirect();
    expect($set->refresh()->student_signup_enabled)->toBeFalse();

    $this->patch(route('student-sets.update', $parameters), ['student_signup_enabled' => true, 'current_academic_year_id' => $year->id])
        ->assertRedirect();
    expect($set->refresh()->student_signup_enabled)->toBeTrue();

    $this->patch(route('student-sets.invite.rotate', $parameters))
        ->assertRedirect();
    expect($set->refresh()->invite_token)->not->toBe($originalToken);

    $this->get(route('student-invitations.show', ['studentSet' => $originalToken]))
        ->assertNotFound();
});

test('an adviser cannot manage another advisers signup link', function () {
    $advisor = AdvisorProfile::factory()->create();
    $otherSet = StudentSet::factory()->create();

    $this->actingAs($advisor->user)
        ->patch(route('student-sets.invite.rotate', $otherSet->routeParameters()))
        ->assertNotFound();

    expect($otherSet->refresh()->student_signup_enabled)->toBeTrue();
});
