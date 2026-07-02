<?php

use App\AccountType;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\School;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

function provisionRegistrationDepartment(string $abbreviation = 'FUTO'): Department
{
    config(['app.default_school_abbreviation' => $abbreviation]);

    $school = School::factory()->create([
        'abbreviation' => $abbreviation,
    ]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
    ]);

    return Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
}

function provisionPendingStaff(Department $department, string $email = 'staff@example.com', string $accessCode = 'STAFF-CODE'): User
{
    $user = User::factory()
        ->staff()
        ->pendingActivation($accessCode)
        ->create([
            'school_id' => $department->faculty->school_id,
            'name' => 'Staff User',
            'email' => $email,
        ]);

    StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    return $user;
}

function provisionPendingStudent(Department $department, string $registrationNumber = '2021001234', string $accessCode = 'STUDENT-CODE'): User
{
    $user = User::factory()
        ->student()
        ->pendingActivation($accessCode)
        ->create([
            'school_id' => $department->faculty->school_id,
            'name' => 'Student User',
            'email' => null,
        ]);

    StudentProfile::factory()->create([
        'school_id' => $department->faculty->school_id,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'registration_number' => $registrationNumber,
    ]);

    return $user;
}

test('students verify access before activating with an email and password', function () {
    $department = provisionRegistrationDepartment();
    $user = provisionPendingStudent($department);

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

test('staff verify access before activating with a password', function () {
    $department = provisionRegistrationDepartment();
    $user = provisionPendingStaff($department);

    $this->post(route('signup.staff.verify'), [
        'email' => 'staff@example.com',
        'access_code' => 'STAFF-CODE',
    ])->assertRedirect(route('signup.staff'));

    $this->get(route('signup.staff'))
        ->assertInertia(fn ($page) => $page
            ->where('activation.step', 'complete')
            ->where('activation.identifier', 'staff@example.com')
            ->etc());

    $response = $this->post(route('signup.staff.store'), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user->refresh();

    $this->assertAuthenticatedAs($user);
    expect($user->account_type)->toBe(AccountType::Staff)
        ->and($user->email)->toBe('staff@example.com')
        ->and($user->activated_at)->not->toBeNull()
        ->and($user->access_code)->toBe('STAFF-CODE')
        ->and($user->access_code_used_at)->not->toBeNull()
        ->and(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->staffProfile)->not->toBeNull();
});

test('legacy signup route redirects to the student signup page', function () {
    $response = $this->get(route('signup'));

    $response->assertRedirect(route('signup.student'));
});

test('student verification requires a registration number', function () {
    provisionRegistrationDepartment();

    $response = $this->from(route('signup.student'))->post(route('signup.student.verify'), [
        'access_code' => 'STUDENT-CODE',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('registration_number');
});

test('student verification rejects unknown registration numbers', function () {
    provisionRegistrationDepartment();

    $response = $this->from(route('signup.student'))->post(route('signup.student.verify'), [
        'registration_number' => '2021999999',
        'access_code' => 'STUDENT-CODE',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('registration_number');
});

test('student verification rejects invalid access codes', function () {
    $department = provisionRegistrationDepartment();
    provisionPendingStudent($department);

    $response = $this->from(route('signup.student'))->post(route('signup.student.verify'), [
        'registration_number' => '2021001234',
        'access_code' => 'WRONG-CODE',
    ]);

    $response->assertRedirect(route('signup.student'));
    $response->assertSessionHasErrors('access_code');
});

test('student activation requires verified access first', function () {
    provisionRegistrationDepartment();

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
    $department = provisionRegistrationDepartment();
    $user = provisionPendingStaff($department);

    $user->forceFill([
        'activated_at' => now(),
    ])->save();

    $response = $this->from(route('signup.staff'))->post(route('signup.staff.verify'), [
        'email' => 'staff@example.com',
        'access_code' => 'STAFF-CODE',
    ]);

    $response->assertRedirect(route('signup.staff'));
    $response->assertSessionHasErrors('email');
});

test('staff verification rejects unknown emails', function () {
    provisionRegistrationDepartment();

    $response = $this->from(route('signup.staff'))->post(route('signup.staff.verify'), [
        'email' => 'unknown@example.com',
        'access_code' => 'STAFF-CODE',
    ]);

    $response->assertRedirect(route('signup.staff'));
    $response->assertSessionHasErrors('email');
});

test('emails and registration numbers can repeat across different schools', function () {
    $firstDepartment = provisionRegistrationDepartment('FUTO');
    $secondDepartment = provisionRegistrationDepartment('MIT');

    $firstStaff = provisionPendingStaff($firstDepartment, 'shared@example.com', 'FUTO-CODE');
    $secondStaff = provisionPendingStaff($secondDepartment, 'shared@example.com', 'MIT-CODE');
    $firstStudent = provisionPendingStudent($firstDepartment, '2021001234', 'FUTO-STUDENT');
    $secondStudent = provisionPendingStudent($secondDepartment, '2021001234', 'MIT-STUDENT');

    User::factory()->student()->create([
        'school_id' => $firstDepartment->faculty->school_id,
        'email' => 'student-shared@example.com',
    ]);
    User::factory()->student()->create([
        'school_id' => $secondDepartment->faculty->school_id,
        'email' => 'student-shared@example.com',
    ]);

    expect($firstStaff->email)->toBe($secondStaff->email)
        ->and($firstStaff->school_id)->not->toBe($secondStaff->school_id)
        ->and($firstStudent->studentProfile->registration_number)->toBe($secondStudent->studentProfile->registration_number)
        ->and($firstStudent->studentProfile->school_id)->not->toBe($secondStudent->studentProfile->school_id);
});

test('emails and registration numbers remain unique within the same school', function () {
    $department = provisionRegistrationDepartment();
    $schoolId = $department->faculty->school_id;

    provisionPendingStaff($department, 'shared@example.com');
    expect(fn () => User::factory()
        ->staff()
        ->pendingActivation()
        ->create([
            'school_id' => $schoolId,
            'email' => 'shared@example.com',
        ]))->toThrow(QueryException::class);

    provisionPendingStudent($department, '2021001234');
    $secondUser = User::factory()
        ->student()
        ->pendingActivation()
        ->create([
            'school_id' => $schoolId,
            'email' => null,
        ]);

    expect(fn () => StudentProfile::factory()->create([
        'school_id' => $schoolId,
        'user_id' => $secondUser->id,
        'department_id' => $department->id,
        'registration_number' => '2021001234',
    ]))->toThrow(QueryException::class);
});

test('student activation rejects emails already used in the same school', function () {
    $department = provisionRegistrationDepartment();
    provisionPendingStudent($department);
    User::factory()->student()->create([
        'school_id' => $department->faculty->school_id,
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

test('staff login is scoped to the current school', function () {
    $firstDepartment = provisionRegistrationDepartment('FUTO');
    $secondDepartment = provisionRegistrationDepartment('MIT');
    $firstStaff = provisionPendingStaff($firstDepartment, 'shared@example.com', 'FUTO-CODE');
    provisionPendingStaff($secondDepartment, 'shared@example.com', 'MIT-CODE');
    config(['app.default_school_abbreviation' => 'FUTO']);

    $firstStaff->forceFill([
        'password' => 'futo-password',
        'access_code' => 'FUTO-CODE',
        'access_code_used_at' => now(),
        'activated_at' => now(),
    ])->save();

    $response = $this->post(route('login.store'), [
        'email' => 'shared@example.com',
        'password' => 'futo-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($firstStaff);
});

test('unactivated users cannot login before signup activation', function () {
    $department = provisionRegistrationDepartment();
    provisionPendingStaff($department, 'pending@example.com', 'PENDING-CODE');

    $response = $this->from(route('home'))->post(route('login.store'), [
        'email' => 'pending@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
