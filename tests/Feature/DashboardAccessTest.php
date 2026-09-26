<?php

use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use Database\Seeders\AcademicStructureSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('course advisers and students receive their own dashboard shell', function () {
    $this->seed(AcademicStructureSeeder::class);
    $advisor = AdvisorProfile::factory()->create();
    $student = StudentProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'adviser')
            ->where('dashboard.section', 'home'));

    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'student')
            ->where('dashboard.section', 'home'));
});

test('dashboard sections are limited to the current account type', function () {
    $advisor = AdvisorProfile::factory()->create();
    $student = StudentProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->get(route('dashboard.section', ['section' => 'settings']))
        ->assertOk();

    $this->actingAs($student->user)
        ->get(route('dashboard.section', ['section' => 'settings']))
        ->assertNotFound();

    $this->actingAs($student->user)
        ->get(route('dashboard.section', ['section' => 'students']))
        ->assertNotFound();
});

test('authenticated users can log out', function () {
    $advisor = AdvisorProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
