<?php

use App\Models\AssistantConversation;
use App\Models\CourseRegistration;
use App\Models\DuePayment;
use App\Services\AssistantRecordSearch;
use App\Services\AssistantRecordTool;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Tools\Request as ToolRequest;

test('adviser activity only includes registrations from their own students', function () {
    $own = CourseRegistration::factory()->create([
        'status' => CourseRegistration::StatusSubmitted,
        'submitted_at' => now(),
    ]);
    $other = CourseRegistration::factory()->create([
        'status' => CourseRegistration::StatusSubmitted,
        'submitted_at' => now(),
    ]);
    $advisor = $own->studentProfile->advisorProfile;
    $semester = $own->academicSessionSemester;
    $levelSemester = $semester->academicSession->academicYear->name.' '.$semester->semester->name;
    $session = $semester->academicSession->label().' Session';

    $this->actingAs($advisor->user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserHome.pendingCount', 1)
            ->where('dashboard.adviserHome.pendingRegistrations.0.id', $own->id)
            ->where('dashboard.adviserHome.pendingRegistrations.0.levelSemester', $levelSemester)
            ->where('dashboard.adviserHome.pendingRegistrations.0.session', $session));

    $this->get(route('dashboard.course-registration.activity'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserRegistrationActivity.registrations.total', 1)
            ->where('dashboard.adviserRegistrationActivity.registrations.data.0.id', $own->id)
            ->where('dashboard.adviserRegistrationActivity.registrations.data.0.levelSemester', $levelSemester)
            ->where('dashboard.adviserRegistrationActivity.registrations.data.0.session', $session));

    $this->get(route('dashboard.course-registration.show', $other->reviewRouteParameters()))->assertNotFound();
});

test('assistant conversations belong only to their adviser', function () {
    $owner = CourseRegistration::factory()->create()->studentProfile->advisorProfile;
    $visitor = CourseRegistration::factory()->create()->studentProfile->advisorProfile;
    $conversation = AssistantConversation::query()->create([
        'advisor_profile_id' => $owner->id,
        'title' => 'Private question',
    ]);

    $this->actingAs($owner->user)
        ->get(route('dashboard.assistant.show', ['assistantConversation' => $conversation]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserAssistant.selectedId', $conversation->public_id));

    $this->actingAs($visitor->user)
        ->get(route('dashboard.assistant.show', ['assistantConversation' => $conversation]))
        ->assertNotFound();

    $this->post(route('dashboard.assistant.reply', ['assistantConversation' => $conversation]), [
        'question' => 'Show students',
    ])->assertNotFound();
});

test('payment history only shows completed payments belonging to the adviser', function () {
    $own = DuePayment::factory()->create(['status' => DuePayment::StatusSuccess, 'paid_at' => now()]);
    DuePayment::factory()->create(['status' => DuePayment::StatusSuccess, 'paid_at' => now()]);
    DuePayment::factory()->create([
        'student_due_assignment_id' => $own->student_due_assignment_id,
        'student_profile_id' => $own->student_profile_id,
        'advisor_profile_id' => $own->advisor_profile_id,
    ]);

    $this->actingAs($own->advisorProfile->user)
        ->get(route('dashboard.student-dues.payment-history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserPaymentHistory.payments.total', 1)
            ->where('dashboard.adviserPaymentHistory.payments.data.0.id', $own->id));
});

test('assistant tool searches cannot read another advisers students', function () {
    $own = CourseRegistration::factory()->create()->studentProfile;
    $other = CourseRegistration::factory()->create()->studentProfile;
    $tool = new AssistantRecordTool($own->advisorProfile, app(AssistantRecordSearch::class));
    $payload = $tool->handle(new ToolRequest(['category' => 'students', 'term' => '', 'status' => 'any']));

    expect($payload)->toContain($own->user->name)
        ->not->toContain($other->user->name)
        ->and($tool->sources())->toHaveCount(1);
});
