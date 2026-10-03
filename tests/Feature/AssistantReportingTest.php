<?php

use App\Jobs\AnswerAssistantQuestion;
use App\Models\AdvisorProfile;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\AssistantReportQuery;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Queue;

test('assistant reporting ranks the complete adviser roster and stays within its owner', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu.ng')->firstOrFail()->advisorProfile;
    $reports = app(AssistantReportQuery::class);

    $top = $reports->run($adviser, [
        'dataset' => 'students', 'select' => ['name', 'registration_number', 'cgpa'],
        'sort_by' => 'cgpa', 'direction' => 'desc', 'limit' => 5,
    ]);
    $next = $reports->run($adviser, [
        'dataset' => 'students', 'select' => ['name', 'registration_number', 'cgpa'],
        'sort_by' => 'cgpa', 'direction' => 'desc', 'limit' => 5, 'page' => 2,
    ]);
    $byStatus = $reports->run($adviser, [
        'dataset' => 'students', 'group_by' => 'status', 'aggregate' => 'count',
    ]);
    $other = $reports->run(AdvisorProfile::factory()->create(), [
        'dataset' => 'students', 'aggregate' => 'count',
    ]);

    expect($top['matched'])->toBe(90)
        ->and($top['returned'])->toBe(5)
        ->and($top['more_available'])->toBeTrue()
        ->and($top['complete_population'])->toBeTrue()
        ->and(collect($top['rows'])->pluck('cgpa')->all())->toBe(collect($top['rows'])->pluck('cgpa')->sortDesc()->values()->all())
        ->and($top['rows'][4]['cgpa'])->toBeGreaterThanOrEqual($next['rows'][0]['cgpa'])
        ->and(collect($byStatus['rows'])->sum('value'))->toBe(90)
        ->and($byStatus['sources'])->toHaveCount(1)
        ->and($byStatus['sources'][0]['records'])->toBe(90)
        ->and($byStatus['sources'][0]['url'])->toBe(route('student-sets.show', $adviser->studentSets()->firstOrFail()->routeParameters()))
        ->and($top['sources'])->toBe([])
        ->and($other['matched'])->toBe(0);
});

test('student reports cannot see other students or locked result periods', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $student = User::query()->where('email', 'student3@futo.edu.ng')->firstOrFail()->studentProfile;
    $reports = app(AssistantReportQuery::class);

    $self = $reports->run($student, ['dataset' => 'students']);
    $locked = $reports->run($student, [
        'dataset' => 'results',
        'filters' => [
            ['field' => 'session', 'operator' => 'eq', 'value' => '2024/2025 Session'],
            ['field' => 'semester', 'operator' => 'eq', 'value' => 'first'],
        ],
    ]);

    expect($self['matched'])->toBe(1)
        ->and($self['rows'][0]['registration_number'])->toBe($student->registration_number)
        ->and($locked['matched'])->toBe(0);

    $reports->run($student, ['dataset' => 'dues']);
})->throws(InvalidArgumentException::class);

test('assistant questions are queued once and failed answers can be retried', function () {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Queue::fake();
    $adviser = AdvisorProfile::factory()->create();
    $this->actingAs($adviser->user);

    $this->post(route('dashboard.assistant.store'), ['question' => 'Who are my top students?'])
        ->assertRedirect();
    $conversation = AssistantConversation::query()->firstOrFail();
    $answer = $conversation->messages()->where('role', 'assistant')->firstOrFail();
    expect($answer->status)->toBe(AssistantMessage::StatusQueued)
        ->and($conversation->messages()->count())->toBe(2);
    Queue::assertPushed(AnswerAssistantQuestion::class, 1);

    $this->post(route('dashboard.assistant.reply', ['assistantConversation' => $conversation]), [
        'question' => 'And how many students?',
    ])->assertRedirect();
    expect($conversation->messages()->count())->toBe(2);

    $answer->update(['status' => AssistantMessage::StatusFailed]);
    $this->post(route('dashboard.assistant.retry', ['assistantConversation' => $conversation]))
        ->assertRedirect();
    expect($answer->fresh()->status)->toBe(AssistantMessage::StatusQueued);
    Queue::assertPushed(AnswerAssistantQuestion::class, 2);
});
