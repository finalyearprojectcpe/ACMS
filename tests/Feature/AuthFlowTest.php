<?php

use App\AccountType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('guests are redirected from the dashboard to login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('home'));
});

test('authenticated users are redirected away from login and signup', function (string $route) {
    $user = User::factory()->student()->create();

    $response = $this->actingAs($user)->get(route($route));

    $response->assertRedirect(route('dashboard'));
})->with(['home', 'signup', 'signup.advisor']);

test('demo users are seeded with profiles and can login', function () {
    $this->seed(DatabaseSeeder::class);

    $adviser = User::query()
        ->where('email', 'staff1@futo.edu')
        ->firstOrFail();
    $student = User::query()
        ->where('email', 'student1@futo.edu')
        ->firstOrFail();

    expect($adviser->account_type)->toBe(AccountType::Adviser)
        ->and($adviser->advisorProfile)->not->toBeNull()
        ->and($adviser->advisorProfile->studentSets()->count())->toBeGreaterThanOrEqual(1)
        ->and($student->account_type)->toBe(AccountType::Student)
        ->and($student->studentProfile)->not->toBeNull()
        ->and($student->studentProfile->registration_number)->toBe('2024000001');

    $response = $this->post(route('login.store'), [
        'email' => 'staff1@futo.edu',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($adviser);
});
