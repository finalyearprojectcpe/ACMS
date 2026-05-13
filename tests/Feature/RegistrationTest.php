<?php

use App\AccountType;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;

test('students can create an account', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();

    $response = $this->post(route('signup.store'), [
        'account_type' => AccountType::Student->value,
        'surname' => 'Student',
        'other_names' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'password',
        'faculty_id' => $department->faculty_id,
        'department_id' => $department->id,
        'registration_number' => '2021001234',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->account_type)->toBe(AccountType::Student)
        ->and($user->name)->toBe('Student Jane')
        ->and($user->studentProfile)->not->toBeNull()
        ->and($user->studentProfile->department_id)->toBe($department->id)
        ->and($user->studentProfile->currentLevel->slug)->toBe('100-level')
        ->and($user->studentProfile->registration_number)->toBe('2021001234')
        ->and($user->staffProfile)->toBeNull();
});

test('staff can create an account', function () {
    $department = Department::factory()->create();

    $response = $this->post(route('signup.store'), [
        'account_type' => AccountType::Staff->value,
        'surname' => 'Staff',
        'other_names' => 'John',
        'email' => 'staff@example.com',
        'password' => 'password',
        'faculty_id' => $department->faculty_id,
        'department_id' => $department->id,
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'staff@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->account_type)->toBe(AccountType::Staff)
        ->and($user->name)->toBe('Staff John')
        ->and($user->staffProfile)->not->toBeNull()
        ->and($user->staffProfile->department_id)->toBe($department->id)
        ->and($user->studentProfile)->toBeNull();
});

test('student registration requires a registration number', function () {
    $department = Department::factory()->create();

    $response = $this->from(route('signup'))->post(route('signup.store'), [
        'account_type' => AccountType::Student->value,
        'surname' => 'Student',
        'other_names' => 'Jane',
        'email' => 'missing-reg@example.com',
        'password' => 'password',
        'faculty_id' => $department->faculty_id,
        'department_id' => $department->id,
    ]);

    $response->assertRedirect(route('signup'));
    $response->assertSessionHasErrors('registration_number');
});

test('student registration numbers must be globally unique', function () {
    $department = Department::factory()->create();

    StudentProfile::factory()->create([
        'registration_number' => '2021001234',
    ]);

    $response = $this->from(route('signup'))->post(route('signup.store'), [
        'account_type' => AccountType::Student->value,
        'surname' => 'Student',
        'other_names' => 'Jane',
        'email' => 'duplicate-reg@example.com',
        'password' => 'password',
        'faculty_id' => $department->faculty_id,
        'department_id' => $department->id,
        'registration_number' => '2021001234',
    ]);

    $response->assertRedirect(route('signup'));
    $response->assertSessionHasErrors('registration_number');
});

test('staff registration rejects registration numbers', function () {
    $department = Department::factory()->create();

    $response = $this->from(route('signup'))->post(route('signup.store'), [
        'account_type' => AccountType::Staff->value,
        'surname' => 'Staff',
        'other_names' => 'John',
        'email' => 'staff-reg@example.com',
        'password' => 'password',
        'faculty_id' => $department->faculty_id,
        'department_id' => $department->id,
        'registration_number' => '2021001234',
    ]);

    $response->assertRedirect(route('signup'));
    $response->assertSessionHasErrors('registration_number');
});

test('registration validates account type and department', function () {
    $response = $this->from(route('signup'))->post(route('signup.store'), [
        'account_type' => 'guardian',
        'surname' => 'Invalid',
        'other_names' => 'User',
        'email' => 'invalid@example.com',
        'password' => 'password',
        'faculty_id' => 999,
        'department_id' => 999,
    ]);

    $response->assertRedirect(route('signup'));
    $response->assertSessionHasErrors(['account_type', 'faculty_id', 'department_id']);
});

test('registration validates that departments belong to the selected faculty', function () {
    $department = Department::factory()->create();
    $otherFaculty = Faculty::factory()->create();

    $response = $this->from(route('signup'))->post(route('signup.store'), [
        'account_type' => AccountType::Student->value,
        'surname' => 'Student',
        'other_names' => 'Jane',
        'email' => 'wrong-faculty@example.com',
        'password' => 'password',
        'faculty_id' => $otherFaculty->id,
        'department_id' => $department->id,
        'registration_number' => '2021009999',
    ]);

    $response->assertRedirect(route('signup'));
    $response->assertSessionHasErrors('department_id');
});

test('student registration numbers must contain only numbers', function () {
    $department = Department::factory()->create();

    $response = $this->from(route('signup'))->post(route('signup.store'), [
        'account_type' => AccountType::Student->value,
        'surname' => 'Student',
        'other_names' => 'Jane',
        'email' => 'non-numeric-reg@example.com',
        'password' => 'password',
        'faculty_id' => $department->faculty_id,
        'department_id' => $department->id,
        'registration_number' => 'CPE/2026/001',
    ]);

    $response->assertRedirect(route('signup'));
    $response->assertSessionHasErrors('registration_number');
});

test('users can login with email and password', function () {
    $user = User::factory()->staff()->create([
        'email' => 'login@example.com',
    ]);

    StaffProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'login@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});
