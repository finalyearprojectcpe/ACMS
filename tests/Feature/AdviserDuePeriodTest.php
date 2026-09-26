<?php

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\Due;
use App\Models\Semester;
use App\Models\StudentSet;
use Inertia\Testing\AssertableInertia as Assert;

test('a due uses the chosen set session and semester', function () {
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $session = AcademicSession::factory()->create(['student_set_id' => $set->id]);
    $semester = AcademicSessionSemester::factory()->create(['academic_session_id' => $session->id]);

    $this->actingAs($advisor->user)->post(route('dashboard.dues.store'), [
        'student_set_id' => $set->id,
        'academic_session_id' => $session->id,
        'academic_session_semester_id' => $semester->id,
        'title' => 'Class Dues',
        'amount_naira' => 5000,
        'action' => 'draft',
    ])->assertRedirect();

    $due = Due::query()->sole();
    expect($due->student_set_id)->toBe($set->id)
        ->and($due->academic_session_id)->toBe($session->id)
        ->and($due->academic_session_semester_id)->toBe($semester->id);

    $this->get(route('student-sets.dues.semester', [
        ...$set->routeParameters(),
        'sessionYears' => $session->routeYears(),
        'semester' => $semester->semester->slug,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserDues.selectedSet.id', $set->id)
            ->where('dashboard.adviserDues.selectedPeriod.kind', 'semester')
            ->where('dashboard.adviserDues.selectedPeriod.id', $semester->id)
            ->has('dashboard.adviserDues.dues', 1));

    $this->get(route('student-sets.dues.show', $set->routeParameters()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserDues.selectedPeriod.kind', 'all')
            ->has('dashboard.adviserDues.dues', 1));

    $this->get(route('student-sets.dues.semester.create', [
        ...$set->routeParameters(),
        'sessionYears' => $session->routeYears(),
        'semester' => $semester->semester->slug,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserDueForm.defaults.studentSetId', $set->id)
            ->where('dashboard.adviserDueForm.defaults.academicSessionId', $session->id)
            ->where('dashboard.adviserDueForm.defaults.academicSessionSemesterId', $semester->id));
});

test('whole-session dues have their own view and do not appear in semester views', function () {
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $firstYear = AcademicYear::query()->firstWhere('year_number', 1);
    $secondYear = AcademicYear::factory()->create(['name' => '200 Level', 'slug' => '200-level', 'year_number' => 2]);
    $session = AcademicSession::factory()->create(['student_set_id' => $set->id, 'academic_year_id' => $firstYear->id]);
    $semester = AcademicSessionSemester::factory()->create(['academic_session_id' => $session->id]);
    $otherSession = AcademicSession::factory()->create(['student_set_id' => $set->id, 'academic_year_id' => $secondYear->id]);

    foreach ([
        [$session->id, null, 'Session Due'],
        [$session->id, $semester->id, 'Semester Due'],
        [$otherSession->id, null, 'Other Session Due'],
    ] as [$sessionId, $semesterId, $title]) {
        Due::query()->create([
            'advisor_profile_id' => $advisor->id,
            'student_set_id' => $set->id,
            'academic_session_id' => $sessionId,
            'academic_session_semester_id' => $semesterId,
            'title' => $title,
            'amount_kobo' => 500000,
            'status' => Due::StatusDraft,
        ]);
    }

    $this->actingAs($advisor->user)->get(route('student-sets.dues.session', [
        ...$set->routeParameters(),
        'sessionYears' => $session->routeYears(),
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserDues.selectedPeriod.kind', 'session')
            ->where('dashboard.adviserDues.selectedPeriod.id', $session->id)
            ->has('dashboard.adviserDues.dues', 1)
            ->where('dashboard.adviserDues.dues.0.title', 'Session Due'));

    $this->get(route('student-sets.dues.semester', [
        ...$set->routeParameters(),
        'sessionYears' => $session->routeYears(),
        'semester' => $semester->semester->slug,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserDues.selectedPeriod.kind', 'semester')
            ->has('dashboard.adviserDues.dues', 1)
            ->where('dashboard.adviserDues.dues.0.title', 'Semester Due'));

    $url = route('student-sets.dues.show', $set->routeParameters());
    $this->get($url.'?semester='.$semester->public_id)->assertNotFound();
    $this->get($url.'?session='.$session->public_id)->assertNotFound();
    $otherSet = StudentSet::factory()->create();
    $otherSetSession = AcademicSession::factory()->create(['student_set_id' => $otherSet->id]);
    $otherSetSemester = AcademicSessionSemester::factory()->create(['academic_session_id' => $otherSetSession->id]);
    $this->get(route('student-sets.dues.semester', [
        ...$otherSet->routeParameters(),
        'sessionYears' => $otherSetSession->routeYears(),
        'semester' => $otherSetSemester->semester->slug,
    ]))->assertNotFound();
});

test('a due cannot point to another set session or an unrelated semester', function () {
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $otherSet = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id, 'department_name' => 'Other Department', 'department_slug' => 'other-department']);
    $session = AcademicSession::factory()->create(['student_set_id' => $set->id]);
    $otherSession = AcademicSession::factory()->create(['student_set_id' => $otherSet->id]);
    $otherSemester = AcademicSessionSemester::factory()->create(['academic_session_id' => $otherSession->id]);
    $payload = [
        'student_set_id' => $set->id,
        'academic_session_id' => $otherSession->id,
        'academic_session_semester_id' => null,
        'title' => 'Class Dues',
        'amount_naira' => 5000,
        'action' => 'draft',
    ];

    $this->actingAs($advisor->user)->post(route('dashboard.dues.store'), $payload)
        ->assertSessionHasErrors('academic_session_id');
    $this->post(route('dashboard.dues.store'), [
        ...$payload,
        'academic_session_id' => $session->id,
        'academic_session_semester_id' => $otherSemester->id,
    ])->assertSessionHasErrors('academic_session_semester_id');
    expect(Due::query()->count())->toBe(0);
});

test('published dues retain their academic period', function () {
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create(['advisor_profile_id' => $advisor->id]);
    $session = AcademicSession::factory()->create(['student_set_id' => $set->id]);
    $first = AcademicSessionSemester::factory()->create(['academic_session_id' => $session->id]);
    $secondSemester = Semester::factory()->create([
        'sort_order' => $first->semester->sort_order === 1 ? 2 : 1,
        'slug' => $first->semester->sort_order === 1 ? 'second' : 'first',
        'name' => $first->semester->sort_order === 1 ? '2nd Semester' : '1st Semester',
    ]);
    $second = AcademicSessionSemester::factory()->create(['academic_session_id' => $session->id, 'semester_id' => $secondSemester->id]);
    $due = Due::query()->create([
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
        'academic_session_id' => $session->id,
        'academic_session_semester_id' => $first->id,
        'title' => 'Class Dues',
        'amount_kobo' => 500000,
        'status' => Due::StatusPublished,
    ]);

    $this->actingAs($advisor->user)->patch(route('dashboard.dues.update', ['due' => $due]), [
        'student_set_id' => $set->id,
        'academic_session_id' => $session->id,
        'academic_session_semester_id' => $second->id,
        'title' => 'Class Dues',
        'amount_naira' => 5000,
        'action' => 'draft',
    ])->assertSessionHasErrors('academic_session_id');

    expect($due->refresh()->academic_session_semester_id)->toBe($first->id);
});
