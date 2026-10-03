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
use App\Services\AdviserJoinRequestData;
use App\Services\DashboardPresenter;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('course advisers create an empty workspace', function () {
    $this->post(route('signup.advisor.store'), [
        'title' => Honorific::Dr->value,
        'name' => 'Okoro Chinedu',
        'email' => 'adviser@futo.edu',
        'password' => 'abc123',
        'password_confirmation' => 'abc123',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'adviser@futo.edu')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->account_type)->toBe(AccountType::Adviser)
        ->and($user->displayName())->toBe('Dr Okoro Chinedu')
        ->and(AcademicSession::query()->count())->toBe(0)
        ->and($user->advisorProfile->studentSets()->count())->toBe(0);
});

test('passwords need six characters with letters and numbers', function () {
    foreach (['abc12', 'abcdef', '123456'] as $password) {
        $this->post(route('signup.advisor.store'), [
            'title' => Honorific::Dr->value,
            'name' => 'Okoro Chinedu',
            'email' => 'adviser@futo.edu',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');
    }
});

test('the adviser owns the class list and registration numbers are unique within it', function () {
    $set = StudentSet::factory()->create();
    $params = $set->routeParameters();

    $this->actingAs($set->advisorProfile->user)->post(route('student-sets.students.store', $params), [
        'official_name' => 'Adaeze Nwosu',
        'registration_number' => 'CPE/24/007',
    ])->assertRedirect(route('student-sets.show', $params));

    $student = $set->students()->firstOrFail();
    expect($student->official_name)->toBe('Adaeze Nwosu')
        ->and($student->user_id)->toBeNull()
        ->and($student->registration_number_key)->toBe('cpe/24/007');

    $this->post(route('student-sets.students.store', $params), [
        'official_name' => 'Another Student',
        'registration_number' => 'cpe/24/007',
    ])->assertSessionHasErrors('registration_number');

    $other = AdvisorProfile::factory()->create();
    $this->actingAs($other->user)->patch(route('student-sets.students.update', [
        ...$params,
        'registrationNumber' => $student->routeRegistrationNumber(),
    ]), [
        'official_name' => 'Changed',
        'registration_number' => 'CPE/24/007',
    ])->assertNotFound();
});

test('an invite requires an exact unclaimed class list match before account creation', function () {
    $set = StudentSet::factory()->create();
    $student = StudentProfile::factory()->unclaimed()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'official_name' => 'Adaeze Nwosu',
        'registration_number' => 'CPE/24/007',
    ]);
    $invite = ['studentSet' => $set->invite_token];

    $this->post(route('student-invitations.lookup', $invite), [
        'registration_number' => 'missing',
    ])->assertSessionHasErrors('registration_number');

    $this->post(route('student-invitations.lookup', $invite), [
        'registration_number' => 'CPE/24/007',
    ])->assertRedirect(route('student-invitations.show', $invite));

    $this->get(route('student-invitations.show', $invite))
        ->assertInertia(fn (Assert $page) => $page
            ->component('student-invitation')
            ->where('invitation.student.name', 'Adaeze Nwosu'));

    $this->post(route('student-invitations.store', $invite), [
        'email' => 'adaeze@futo.edu',
        'password' => 'join12',
        'password_confirmation' => 'join12',
    ])->assertRedirect(route('student-joins.status'));

    $user = User::query()->where('email', 'adaeze@futo.edu')->firstOrFail();
    $join = $user->studentJoinRequest;
    expect($user->name)->toBe('Adaeze Nwosu')
        ->and(Hash::check('join12', $user->password))->toBeTrue()
        ->and($user->studentProfile)->toBeNull()
        ->and($join->student_profile_id)->toBe($student->id)
        ->and($join->status)->toBe(StudentJoinRequest::StatusPending);

    auth()->logout();
    $this->post(route('student-invitations.lookup', $invite), [
        'registration_number' => 'CPE/24/007',
    ])->assertSessionHasErrors('registration_number');
});

test('pending students may correct email but not the matched registration number', function () {
    $set = StudentSet::factory()->create();
    $student = StudentProfile::factory()->unclaimed()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'registration_number' => 'CPE/24/007',
    ]);
    $user = User::factory()->student()->create(['email_verified_at' => null]);
    StudentJoinRequest::factory()->create([
        'user_id' => $user->id,
        'student_profile_id' => $student->id,
    ]);

    $this->actingAs($user)->patch(route('student-joins.update'), [
        'email' => 'corrected@futo.edu',
        'registration_number' => 'CPE/24/008',
    ])->assertSessionHasErrors('registration_number');

    expect($student->fresh()->registration_number)->toBe('CPE/24/007');

    $this->patch(route('student-joins.update'), [
        'email' => 'corrected@futo.edu',
    ])->assertRedirect(route('student-joins.status'));

    expect($user->fresh()->email)->toBe('corrected@futo.edu')
        ->and($student->fresh()->registration_number)->toBe('CPE/24/007');
});

test('a declined student may select a different listed registration number', function () {
    $set = StudentSet::factory()->create();
    $original = StudentProfile::factory()->unclaimed()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
    ]);
    $correct = StudentProfile::factory()->unclaimed()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
    ]);
    $user = User::factory()->student()->create();
    $join = StudentJoinRequest::factory()->create([
        'user_id' => $user->id,
        'student_profile_id' => $original->id,
        'status' => StudentJoinRequest::StatusRejected,
    ]);

    $this->actingAs($user)->patch(route('student-joins.update'), [
        'email' => $user->email,
        'registration_number' => $correct->registration_number,
    ])->assertRedirect(route('student-joins.status'));

    $join->refresh();
    expect($join->student_profile_id)->toBe($correct->id)
        ->and($join->status)->toBe(StudentJoinRequest::StatusPending)
        ->and($user->fresh()->name)->toBe($correct->official_name);
});

test('an adviser cannot see a student email before verification', function () {
    $set = StudentSet::factory()->create();
    $student = StudentProfile::factory()->unclaimed()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
    ]);
    $user = User::factory()->student()->create(['email_verified_at' => null]);
    StudentJoinRequest::factory()->create([
        'user_id' => $user->id,
        'student_profile_id' => $student->id,
    ]);

    $row = app(DashboardPresenter::class)->adviserStudent($student);
    expect($row['email'])->toBeNull()
        ->and($row['emailUnverified'])->toBeTrue()
        ->and(app(AdviserJoinRequestData::class)->home($set->advisorProfile)['requestCount'])->toBe(0);

    $user->forceFill(['email_verified_at' => now()])->save();
    $row = app(DashboardPresenter::class)->adviserStudent($student->fresh());
    expect($row['email'])->toBe($user->email)
        ->and($row['emailUnverified'])->toBeFalse()
        ->and(app(AdviserJoinRequestData::class)->home($set->advisorProfile)['requestCount'])->toBe(1);
});

test('only the owning adviser accepts a verified claim', function () {
    $set = StudentSet::factory()->create();
    $student = StudentProfile::factory()->unclaimed()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
    ]);
    $user = User::factory()->student()->create(['email_verified_at' => null]);
    $join = StudentJoinRequest::factory()->create([
        'user_id' => $user->id,
        'student_profile_id' => $student->id,
    ]);
    $approve = route('student-joins.adviser.approve', ['studentJoinRequest' => $join]);

    $this->actingAs($set->advisorProfile->user)->patch($approve)->assertSessionHasErrors('request');
    $other = AdvisorProfile::factory()->create();
    $this->actingAs($other->user)->patch($approve)->assertNotFound();

    $user->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($set->advisorProfile->user)->patch($approve)
        ->assertRedirect(route('student-sets.show', $set->routeParameters()));

    expect($student->fresh()->user_id)->toBe($user->id)
        ->and($join->fresh()->status)->toBe(StudentJoinRequest::StatusApproved);
});

test('adviser email changes become active only after the new address verifies', function () {
    $set = StudentSet::factory()->create();
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'official_name' => 'Adaeze Nwosu',
        'registration_number' => 'CPE/24/007',
    ]);
    $oldEmail = $student->user->email;

    $this->actingAs($set->advisorProfile->user)->patch(route('student-sets.students.update', [
        ...$set->routeParameters(),
        'registrationNumber' => $student->routeRegistrationNumber(),
    ]), [
        'official_name' => 'Adaeze Nwosu',
        'registration_number' => 'CPE/24/007',
        'email' => 'new@futo.edu',
    ])->assertRedirect(route('student-sets.show', $set->routeParameters()));

    $account = $student->user->fresh();
    expect($account->email)->toBe($oldEmail)
        ->and($account->pending_email)->toBe('new@futo.edu');

    $link = EmailDelivery::query()->where('recipient_email', 'new@futo.edu')->firstOrFail()->metadata['url'];
    $this->get($link)->assertOk();
    $account = $student->user->fresh();
    expect($account->email)->toBe('new@futo.edu')
        ->and($account->pending_email)->toBeNull();
});
