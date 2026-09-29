<?php

use App\AccountType;
use App\Honorific;
use App\Models\AcademicSession;
use App\Models\AdvisorProfile;
use App\Models\EmailDelivery;
use App\Models\StudentJoinRequest;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\StudentJoinService;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('course advisers create an empty workspace', function () {
    $response = $this->post(route('signup.advisor.store'), [
        'title' => Honorific::Dr->value,
        'name' => 'Okoro Chinedu',
        'email' => 'adviser@futo.edu',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'adviser@futo.edu')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->account_type)->toBe(AccountType::Adviser)
        ->and($user->title)->toBe(Honorific::Dr)
        ->and($user->displayName())->toBe('Dr Okoro Chinedu')
        ->and(AcademicSession::query()->count())->toBe(0)
        ->and($user->advisorProfile->studentSets()->count())->toBe(0);
});

test('a student requests access through the set link and an adviser approves it', function () {
    $set = StudentSet::factory()->create();

    $this->get(route('student-invitations.show', ['studentSet' => $set->invite_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student-invitation')
            ->where('invitation.available', true)
            ->where('invitation.studentSet', $set->label())
            ->where('invitation.courseAdviser', $set->advisorProfile->user->displayName()));

    $response = $this->post(
        route('student-invitations.store', ['studentSet' => $set->invite_token]),
        [
            'surname' => 'Nwosu',
            'other_names' => 'Adaeze Chioma',
            'registration_number' => 'CPE/24~007',
            'email' => 'adaeze@futo.edu',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ],
    );

    $response->assertRedirect(route('student-joins.status'));

    $user = User::query()->where('email', 'adaeze@futo.edu')->firstOrFail();
    $join = $user->studentJoinRequest;

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Nwosu Adaeze Chioma')
        ->and(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->studentProfile)->toBeNull()
        ->and($join->status)->toBe(StudentJoinRequest::StatusPending);

    $this->get(route('dashboard'))->assertRedirect(route('student-joins.status'));
    $this->actingAs($set->advisorProfile->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserHome.requestCount', 1)
            ->where('dashboard.adviserHome.recentRequests.0.emailVerified', false));
    $this->patch(route('student-joins.adviser.approve', ['studentJoinRequest' => $join]))
        ->assertSessionHasErrors('request');

    $this->actingAs($user);
    $verification = EmailDelivery::query()->where('purpose_key', 'like', 'join-verification-%')->firstOrFail();
    $this->get($verification->metadata['url'])->assertRedirect(route('student-joins.status'));

    $this->actingAs($set->advisorProfile->user)
        ->patch(route('student-joins.adviser.approve', ['studentJoinRequest' => $join]))
        ->assertRedirect(route('dashboard.students.show', ['registrationNumber' => 'CPE~24~~007']));

    $student = $user->fresh()->studentProfile;
    expect($student)->toBeInstanceOf(StudentProfile::class)
        ->and($student->advisor_profile_id)->toBe($set->advisor_profile_id)
        ->and($student->student_set_id)->toBe($set->id)
        ->and($student->registration_number)->toBe('CPE/24~007');

    $this->get(route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]))
        ->assertOk();
    $this->get(route('dashboard.students.statement.preview', ['registrationNumber' => $student->routeRegistrationNumber()]))
        ->assertOk();

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
});

test('closed signup links do not create student accounts', function () {
    $set = StudentSet::factory()->create(['student_signup_enabled' => false]);

    $this->get(route('student-invitations.show', ['studentSet' => $set->invite_token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitation.available', false));

    $this->post(
        route('student-invitations.store', ['studentSet' => $set->invite_token]),
        [
            'surname' => 'Obi',
            'other_names' => 'Ikenna',
            'registration_number' => '2024005678',
            'email' => 'ikenna@futo.edu',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ],
    )->assertSessionHasErrors('invitation');

    expect(User::query()->where('email', 'ikenna@futo.edu')->exists())->toBeFalse();
    $this->assertGuest();
});

test('a claimed registration number is checked by the adviser at approval, not at signup', function () {
    $set = StudentSet::factory()->create();
    $existingUser = User::factory()->student()->create();
    StudentProfile::factory()->create([
        'user_id' => $existingUser->id,
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'registration_number' => '2024001234',
    ]);

    $this->post(
        route('student-invitations.store', ['studentSet' => $set->invite_token]),
        [
            'surname' => 'Eze',
            'other_names' => 'Chiamaka',
            'registration_number' => '2024001234',
            'email' => 'chiamaka@futo.edu',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ],
    )->assertRedirect(route('student-joins.status'));

    $applicant = User::query()->where('email', 'chiamaka@futo.edu')->firstOrFail();
    $join = $applicant->studentJoinRequest;
    $verification = EmailDelivery::query()->where('purpose_key', 'like', 'join-verification-%')->firstOrFail();
    $this->get($verification->metadata['url'])->assertRedirect(route('student-joins.status'));

    $this->actingAs($set->advisorProfile->user)
        ->get(route('student-joins.adviser.show', ['studentJoinRequest' => $join]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserJoinRequest.conflict', true)
            ->where('dashboard.adviserJoinRequest.canApprove', false));
    $this->patch(route('student-joins.adviser.approve', ['studentJoinRequest' => $join]))
        ->assertSessionHasErrors('request');

    expect($applicant->fresh()->studentProfile)->toBeNull();
});

test('only the owning adviser can review a student request', function () {
    $set = StudentSet::factory()->create();
    $join = StudentJoinRequest::factory()->create(['student_set_id' => $set->id]);
    $other = AdvisorProfile::factory()->create();

    $this->actingAs($other->user)
        ->get(route('student-joins.adviser.show', ['studentJoinRequest' => $join]))->assertNotFound();
    $this->patch(route('student-joins.adviser.approve', ['studentJoinRequest' => $join]))->assertNotFound();

    $join->refresh();
    expect($join->status)->toBe(StudentJoinRequest::StatusPending)
        ->and($join->user->studentProfile)->toBeNull();
});

test('a returned request can be corrected without reserving a student registration number', function () {
    $set = StudentSet::factory()->create();
    $user = User::factory()->student()->create(['email_verified_at' => now()]);
    $join = StudentJoinRequest::factory()->create([
        'user_id' => $user->id,
        'student_set_id' => $set->id,
        'registration_number' => '2024001234',
    ]);

    $this->actingAs($set->advisorProfile->user)
        ->patch(route('student-joins.adviser.reject', ['studentJoinRequest' => $join]), [
            'reason' => 'The registration number does not match the class list.',
        ])->assertRedirect(route('student-joins.adviser.index'));

    expect($user->fresh()->studentProfile)->toBeNull()
        ->and($join->fresh()->status)->toBe(StudentJoinRequest::StatusRejected);

    $this->actingAs($user)->patch(route('student-joins.update'), [
        'surname' => 'Nwosu',
        'other_names' => 'Adaeze',
        'email' => $user->email,
        'registration_number' => '2024005678',
    ])->assertRedirect(route('student-joins.status'));

    $join->refresh();
    expect($join->status)->toBe(StudentJoinRequest::StatusPending)
        ->and($join->registration_number)->toBe('2024005678')
        ->and($join->review_note)->toBeNull();
});

test('an unverified applicant can correct an email address without joining the set', function () {
    $set = StudentSet::factory()->create();
    $user = User::factory()->student()->create([
        'email' => 'mistyped@futo.edu',
        'email_verified_at' => null,
    ]);
    $join = StudentJoinRequest::factory()->create([
        'user_id' => $user->id,
        'student_set_id' => $set->id,
    ]);
    app(StudentJoinService::class)->sendVerification($join);
    $oldLink = EmailDelivery::query()->where('recipient_email', 'mistyped@futo.edu')->firstOrFail()->metadata['url'];

    $this->actingAs($user)->patch(route('student-joins.update'), [
        'surname' => 'Nwosu',
        'other_names' => 'Adaeze',
        'email' => 'correct@futo.edu',
        'registration_number' => '2024005678',
    ])->assertRedirect(route('student-joins.status'));

    expect($user->fresh()->email)->toBe('correct@futo.edu')
        ->and($user->studentProfile)->toBeNull()
        ->and($join->fresh()->status)->toBe(StudentJoinRequest::StatusPending)
        ->and(EmailDelivery::query()->where('recipient_email', 'correct@futo.edu')->exists())->toBeTrue();

    $this->get($oldLink)->assertForbidden();
    $newLink = EmailDelivery::query()->where('recipient_email', 'correct@futo.edu')->firstOrFail()->metadata['url'];
    $this->get($newLink)->assertRedirect(route('student-joins.status'));
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('legacy signup redirects to course adviser signup', function () {
    $this->get(route('signup'))->assertRedirect(route('signup.advisor'));
});

test('adviser signup rejects duplicate emails and unsupported titles', function () {
    $adviser = AdvisorProfile::factory()->create();

    $this->from(route('signup.advisor'))->post(route('signup.advisor.store'), [
        'title' => Honorific::Prof->value,
        'name' => 'Second Adviser',
        'email' => $adviser->user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('email');

    $this->from(route('signup.advisor'))->post(route('signup.advisor.store'), [
        'title' => 'Sir',
        'name' => 'Third Adviser',
        'email' => 'third@futo.edu',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('title');
});
