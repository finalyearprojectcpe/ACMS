<?php

use App\AccountType;
use App\Honorific;
use App\Models\AcademicSession;
use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
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

test('a student joins the adviser set through its signup link', function () {
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
            'registration_number' => '2024001234',
            'email' => 'adaeze@futo.edu',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ],
    );

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'adaeze@futo.edu')->firstOrFail();
    $student = $user->studentProfile;

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Nwosu Adaeze Chioma')
        ->and(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($student)->toBeInstanceOf(StudentProfile::class)
        ->and($student->advisor_profile_id)->toBe($set->advisor_profile_id)
        ->and($student->student_set_id)->toBe($set->id)
        ->and($student->registration_number)->toBe('2024001234');
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

test('student registration numbers are unique within one adviser workspace', function () {
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
    )->assertSessionHasErrors('registration_number');

    $otherSet = StudentSet::factory()->create();
    $this->post(
        route('student-invitations.store', ['studentSet' => $otherSet->invite_token]),
        [
            'surname' => 'Eze',
            'other_names' => 'Chiamaka',
            'registration_number' => '2024001234',
            'email' => 'chiamaka@futo.edu',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ],
    )->assertRedirect(route('dashboard'));
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
