<?php

use App\Models\AdvisorProfile;
use App\Models\Due;
use App\Models\StudentDueAssignment;
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

test('a student can only open their own assistant conversations', function () {
    $student = StudentProfile::factory()->create();
    $otherStudent = StudentProfile::factory()->create();
    $conversation = $student->assistantConversations()->create(['title' => 'My courses']);
    $otherConversation = $otherStudent->assistantConversations()->create(['title' => 'Private question']);

    $this->actingAs($student->user)
        ->get(route('dashboard.section', ['section' => 'assistant']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.adviserAssistant.conversations', 1)
            ->where('dashboard.adviserAssistant.conversations.0.title', 'My courses'));
    $this->get(route('dashboard.assistant.show', ['assistantConversation' => $conversation]))->assertOk();
    $this->get(route('dashboard.assistant.show', ['assistantConversation' => $otherConversation]))->assertNotFound();
});

test('student dues render as payable rows with a scoped total', function () {
    $due = Due::factory()->create();
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $due->advisor_profile_id,
        'student_set_id' => $due->student_set_id,
    ]);
    StudentDueAssignment::query()->create([
        'due_id' => $due->id,
        'student_profile_id' => $student->id,
        'amount_kobo' => 1500000,
        'status' => StudentDueAssignment::StatusPending,
    ]);

    $this->actingAs($student->user)
        ->get(route('dashboard.section', ['section' => 'dues']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.studentDues.totalAmount', '₦15,000.00')
            ->where('dashboard.studentDues.unpaidCount', 1)
            ->where('dashboard.studentDues.rows.0.status', 'Unpaid')
            ->where('dashboard.studentDues.rows.0.name', $due->title));
});

test('authenticated users can log out', function () {
    $advisor = AdvisorProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
