<?php

use App\AccountType;
use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

function provisionAdvisorWorkspace(): StudentSet
{
    return StudentSet::factory()->create();
}

function provisionPendingStudent(StudentSet $set, string $registrationNumber = '2021001234', string $accessCode = 'STUDENT-CODE'): User
{
    $user = User::factory()
        ->student()
        ->pendingActivation($accessCode)
        ->create([
            'name' => 'Student User',
            'email' => null,
        ]);

    StudentProfile::factory()->create([
        'user_id' => $user->id,
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'registration_number' => $registrationNumber,
    ]);

    return $user;
}

test('students verify access before activating with an email and password', function () {
    $set = provisionAdvisorWorkspace();
    $user = provisionPendingStudent($set);

    $this->post(route('signup.student.verify'), [
        'registration_number' => '2021001234',
        'access_code' => 'STUDENT-CODE',
    ])->assertRedirect(route('signup.student'));

    $this->get(route('signup.student'))
        ->assertInertia(fn ($page) => $page
            ->where('activation.step', 'complete')
            ->where('activation.identifier', '2021001234')
            ->etc());

    $response = $this->post(route('signup.student.store'), [
        'email' => 'student@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user->refresh();

    $this->assertAuthenticatedAs($user);
    expect($user->account_type)->toBe(AccountType::Student)
        ->and($user->email)->toBe('student@example.com')
        ->and($user->activated_at)->not->toBeNull()
        ->and($user->access_code)->toBe('STUDENT-CODE')
        ->and($user->access_code_used_at)->not->toBeNull()
        ->and(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->studentProfile->registration_number)->toBe('2021001234');
});

test('course advisers sign up directly with their programme details', function () {
    $response = $this->post(route('signup.advisor.store'), [
        'name' => 'Okoro Chinedu',
        'email' => 'adviser@example.com',
        'institution_name' => 'Federal University of Technology',
        'programme_name' => 'Computer Engineering',
        'programme_abbreviation' => 'CPE',
        'minimum_years_to_degree' => 4,
        'maximum_course_units' => 24,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'adviser@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->account_type)->toBe(AccountType::Adviser)
        ->and($user->advisorProfile->programme_abbreviation)->toBe('CPE')
        ->and($user->advisorProfile->minimum_years_to_degree)->toBe(4)
        ->and($user->advisorProfile->academicSessions()->count())->toBe(0)
        ->and($user->advisorProfile->studentSets()->count())->toBe(0);
});

test('adviser signup rejects duplicate emails and invalid programme limits', function () {
    $adviser = AdvisorProfile::factory()->create();

    $this->from(route('signup.advisor'))->post(route('signup.advisor.store'), [
        'name' => 'Second Adviser',
        'email' => $adviser->user->email,
        'programme_name' => 'Computer Engineering',
        'programme_abbreviation' => 'CPE',
        'minimum_years_to_degree' => 4,
        'maximum_course_units' => 24,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('signup.advisor'));

    $this->assertGuest();

    $this->from(route('signup.advisor'))->post(route('signup.advisor.store'), [
        'name' => 'Third Adviser',
        'email' => 'third@example.com',
        'programme_name' => 'Computer Engineering',
        'programme_abbreviation' => 'CPE',
        'minimum_years_to_degree' => 9,
        'maximum_course_units' => 24,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('signup.advisor'));

    $this->assertGuest();
});

test('legacy signup route redirects to the student signup page', function () {
    $response = $this->get(route('signup'));

    $response->assertRedirect(route('signup.student'));
});

test('student verification requires a registration number', function () {
    $response = $this->from(route('signup.student'))->post(route('signup.student.verify'), [
        'access_code' => 'STUDENT-CODE',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('registration_number');
});

test('student verification rejects unknown registration numbers', function () {
    $response = $this->from(route('signup.student'))->post(route('signup.student.verify'), [
        'registration_number' => '2021999999',
        'access_code' => 'STUDENT-CODE',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('registration_number');
});

test('student verification rejects invalid access codes', function () {
    $set = provisionAdvisorWorkspace();
    provisionPendingStudent($set);

    $response = $this->from(route('signup.student'))->post(route('signup.student.verify'), [
        'registration_number' => '2021001234',
        'access_code' => 'WRONG-CODE',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('access_code');
});

test('student activation requires verified access first', function () {
    $response = $this->from(route('signup.student'))->post(route('signup.student.store'), [
        'email' => 'student@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('activation rejects already activated accounts', function () {
    $set = provisionAdvisorWorkspace();
    $user = provisionPendingStudent($set);

    $user->forceFill([
        'activated_at' => now(),
    ])->save();

    $response = $this->from(route('signup.student'))->post(route('signup.student.verify'), [
        'registration_number' => '2021001234',
        'access_code' => 'STUDENT-CODE',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('registration_number');
});

test('registration numbers can repeat across different advisers', function () {
    $firstSet = provisionAdvisorWorkspace();
    $secondSet = provisionAdvisorWorkspace();

    $firstStudent = provisionPendingStudent($firstSet, '2021001234', 'FUTO-STUDENT');
    $secondStudent = provisionPendingStudent($secondSet, '2021001234', 'MIT-STUDENT');

    expect($firstStudent->studentProfile->registration_number)
        ->toBe($secondStudent->studentProfile->registration_number)
        ->and($firstStudent->studentProfile->advisor_profile_id)
        ->not->toBe($secondStudent->studentProfile->advisor_profile_id);
});

test('emails are globally unique and registration numbers are unique per adviser', function () {
    $set = provisionAdvisorWorkspace();
    provisionPendingStudent($set, '2021001234');

    User::factory()->student()->create([
        'email' => 'shared@example.com',
    ]);
    expect(fn () => User::factory()
        ->student()
        ->pendingActivation()
        ->create([
            'email' => 'shared@example.com',
        ]))->toThrow(QueryException::class);

    $secondUser = User::factory()
        ->student()
        ->pendingActivation()
        ->create([
            'email' => null,
        ]);

    expect(fn () => StudentProfile::factory()->create([
        'user_id' => $secondUser->id,
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'registration_number' => '2021001234',
    ]))->toThrow(QueryException::class);
});

test('student activation rejects emails already in use', function () {
    $set = provisionAdvisorWorkspace();
    provisionPendingStudent($set);
    User::factory()->student()->create([
        'email' => 'taken@example.com',
    ]);

    $this->post(route('signup.student.verify'), [
        'registration_number' => '2021001234',
        'access_code' => 'STUDENT-CODE',
    ])->assertRedirect(route('signup.student'));

    $response = $this->from(route('signup.student'))->post(route('signup.student.store'), [
        'email' => 'taken@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('unactivated users cannot login before signup activation', function () {
    $set = provisionAdvisorWorkspace();
    provisionPendingStudent($set, '2021001234', 'PENDING-CODE');

    $response = $this->from(route('home'))->post(route('login.store'), [
        'email' => 'pending@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
