<?php

use App\Models\AcademicSession;
use App\Models\AdvisorProfile;
use App\Models\StudentSet;
use Database\Seeders\AcademicStructureSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('an adviser with no academic setup can open profile settings', function () {
    $advisor = AdvisorProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->get(route('dashboard.section', ['section' => 'profile-settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserSettings.academicSessions', [])
            ->where('dashboard.adviserSettings.assignedSets', [])
            ->where('dashboard.adviserSettings.setupForm', null));
});

test('an adviser creates academic sessions without overlapping dates', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();

    $this->actingAs($advisor->user)->post(
        route('dashboard.profile-settings.academic-sessions.store'),
        [
            'name' => '2025/2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-08-31',
        ],
    )->assertRedirect(route('dashboard.section', ['section' => 'profile-settings']));

    $session = $advisor->academicSessions()->firstOrFail();

    expect($session->name)->toBe('2025/2026')
        ->and($session->semesterInstances)->toHaveCount(2);

    $this->actingAs($advisor->user)->post(
        route('dashboard.profile-settings.academic-sessions.store'),
        [
            'name' => '2026/2027',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-07-31',
        ],
    )->assertSessionHasErrors('starts_on');

    expect($advisor->academicSessions()->count())->toBe(1);
});

test('an adviser creates and updates a student set from an owned session', function () {
    $advisor = AdvisorProfile::factory()->create([
        'minimum_years_to_degree' => 4,
        'maximum_course_units' => 24,
    ]);
    $session = AcademicSession::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'name' => '2025/2026',
    ]);

    $this->actingAs($advisor->user)->post(
        route('dashboard.profile-settings.student-sets.store'),
        [
            'entry_academic_session_id' => $session->id,
            'minimum_years_to_degree' => 4,
            'maximum_course_units' => 24,
        ],
    )->assertRedirect(route('dashboard.section', ['section' => 'profile-settings']));

    $set = StudentSet::query()->firstOrFail();

    expect($set->name)->toBe('2025/2026 Set')
        ->and($set->advisor_profile_id)->toBe($advisor->id);

    $this->actingAs($advisor->user)->patch(
        route('dashboard.profile-settings.student-sets.update', $set),
        [
            'minimum_years_to_degree' => 5,
            'maximum_course_units' => 27,
        ],
    )->assertRedirect(route('dashboard.section', ['section' => 'profile-settings']));

    expect($set->refresh()->minimum_years_to_degree)->toBe(5)
        ->and($set->maximum_course_units)->toBe(27);
});

test('an adviser cannot create a set from another advisers session', function () {
    $advisor = AdvisorProfile::factory()->create();
    $otherSession = AcademicSession::factory()->create();

    $this->actingAs($advisor->user)->post(
        route('dashboard.profile-settings.student-sets.store'),
        [
            'entry_academic_session_id' => $otherSession->id,
            'minimum_years_to_degree' => 4,
            'maximum_course_units' => 24,
        ],
    )->assertNotFound();
});
