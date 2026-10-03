<?php

use App\Models\AssistantConversation;
use App\Models\CourseRegistration;
use App\Models\DuePayment;
use App\Models\User;
use App\Services\AssistantRecordSearch;
use App\Services\AssistantRecordTool;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\DemoUserSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Tools\Request as ToolRequest;

test('adviser home only includes their students needing courses', function () {
    $own = CourseRegistration::factory()->create([
        'status' => CourseRegistration::StatusDraft,
    ]);
    $other = CourseRegistration::factory()->create([
        'status' => CourseRegistration::StatusDraft,
    ]);
    $advisor = $own->studentProfile->advisorProfile;
    $semester = $own->academicSessionSemester;
    $set = $own->studentProfile->studentSet;
    $set->update([
        'current_academic_year_id' => $semester->academicSession->academic_year_id,
        'current_semester_id' => $semester->semester_id,
    ]);

    $this->actingAs($advisor->user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserHome.unassignedCount', 1)
            ->where('dashboard.adviserHome.unassignedStudents.0.registrationNumber', $own->studentProfile->registration_number)
            ->where('dashboard.adviserHome.unassignedStudents.0.profileUrl', route('dashboard.students.show', [
                'registrationNumber' => $own->studentProfile->routeRegistrationNumber(),
            ])));

    $this->get(route('student-sets.assignments.show', [
        ...$set->routeParameters(),
        'sessionYears' => $semester->academicSession->routeYears(),
        'semester' => $semester->semester->slug,
        'registrationNumber' => $other->studentProfile->routeRegistrationNumber(),
    ]))->assertNotFound();
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

    expect($payload)->toContain($own->official_name)
        ->not->toContain($other->official_name)
        ->and($tool->sources())->toHaveCount(1);
});

test('assistant tool distinguishes draft assignments and paginates within one adviser set', function () {
    $own = CourseRegistration::factory()->create(['status' => CourseRegistration::StatusDraft]);
    $other = CourseRegistration::factory()->create(['status' => CourseRegistration::StatusDraft]);
    $advisor = $own->studentProfile->advisorProfile;
    $tool = new AssistantRecordTool($advisor, app(AssistantRecordSearch::class));

    $firstPage = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'registrations',
        'status' => 'draft',
        'set' => $own->studentProfile->studentSet->label(),
        'page' => 1,
    ])), true, flags: JSON_THROW_ON_ERROR);
    $secondPage = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'registrations',
        'status' => 'draft',
        'set' => $own->studentProfile->studentSet->label(),
        'page' => 2,
    ])), true, flags: JSON_THROW_ON_ERROR);

    expect($firstPage['count'])->toBe(1)
        ->and($firstPage['records'][0]['title'])->toContain($own->studentProfile->official_name)
        ->not->toContain($other->studentProfile->official_name)
        ->and($secondPage['records'])->toBe([]);
});

test('assistant record categories return usable scoped search results', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $advisor = User::query()->where('email', 'staff1@futo.edu.ng')->firstOrFail()->advisorProfile;
    $tool = new AssistantRecordTool($advisor, app(AssistantRecordSearch::class));

    foreach (['sets', 'students', 'join_requests', 'registrations', 'catalog', 'courses', 'results', 'performance', 'dues', 'due_assignments', 'payments'] as $category) {
        $result = json_decode((string) $tool->handle(new ToolRequest([
            'category' => $category,
            'page' => 1,
        ])), true, flags: JSON_THROW_ON_ERROR);

        expect($result)->toHaveKeys(['count', 'records', 'page', 'moreAvailable']);
    }

    $firstSemesterDues = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'dues',
        'session' => '20262027',
        'semester' => 'first',
    ])), true, flags: JSON_THROW_ON_ERROR);
    $secondSemesterDues = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'dues',
        'session' => '20262027',
        'semester' => 'second',
    ])), true, flags: JSON_THROW_ON_ERROR);
    $firstTitles = collect($firstSemesterDues['records'])->pluck('title');
    $secondTitles = collect($secondSemesterDues['records'])->pluck('title');

    expect($firstTitles)->toContain('Student Association Levy', 'Departmental Dues 300 Level')
        ->and($secondTitles)->toContain('Student Association Levy')
        ->not->toContain('Departmental Dues 300 Level');

    $historicalPerformance = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'performance',
        'term' => '2024000001',
        'session' => '20242025',
        'semester' => 'first',
    ])), true, flags: JSON_THROW_ON_ERROR);
    expect($historicalPerformance['records'][0]['detail'])->toContain('2024/2025 Session · 1st Semester');
});
