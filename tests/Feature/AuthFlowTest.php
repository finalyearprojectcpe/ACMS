<?php

use App\AccountType;
use App\Models\ApprovalRequest;
use App\Models\EmailDelivery;
use App\Models\ImportBatch;
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
})->with(['home', 'signup', 'signup.student', 'signup.staff']);

test('demo users are seeded with profiles and can login', function () {
    $this->seed(DatabaseSeeder::class);

    $facultyHead = User::query()
        ->where('email', 'facultyhead.seset@futo.edu')
        ->firstOrFail();
    $schoolAdmin = User::query()
        ->where('email', 'schooladmin@futo.edu')
        ->firstOrFail();
    $teacher = User::query()
        ->where('email', 'staff1@futo.edu')
        ->firstOrFail();
    $student = User::query()
        ->where('email', 'student1@futo.edu')
        ->firstOrFail();
    $faculty = $facultyHead->staffProfile->headedFaculty;

    expect($schoolAdmin->account_type)->toBe(AccountType::SchoolAdmin)
        ->and($schoolAdmin->staffProfile)->toBeNull()
        ->and($facultyHead->account_type)->toBe(AccountType::Staff)
        ->and($facultyHead->staffProfile->headedFaculty)->not->toBeNull()
        ->and($teacher->account_type)->toBe(AccountType::Staff)
        ->and($teacher->staffProfile)->not->toBeNull()
        ->and($teacher->staffProfile->department->hod_staff_profile_id)->toBe($teacher->staffProfile->id)
        ->and($student->account_type)->toBe(AccountType::Student)
        ->and($student->studentProfile)->not->toBeNull()
        ->and($student->studentProfile->currentLevel->slug)->toBe('100-level')
        ->and($student->studentProfile->registration_number)->toBe('2025000001');

    expect($faculty->departments()->count())->toBeGreaterThanOrEqual(6)
        ->and($faculty->departments()->whereNotNull('hod_staff_profile_id')->count())->toBeGreaterThanOrEqual(4)
        ->and(User::query()->where('email', 'hod.mce@futo.edu')->firstOrFail()->activated_at)->toBeNull()
        ->and(User::query()->where('email', 'epe.staff2@futo.edu')->firstOrFail()->access_code)->toBe('SESET-EPE-STAFF2')
        ->and(EmailDelivery::query()->where('school_id', $faculty->school_id)->count())->toBeGreaterThanOrEqual(4)
        ->and(ImportBatch::query()->where('school_id', $faculty->school_id)->count())->toBeGreaterThanOrEqual(3)
        ->and(ApprovalRequest::query()->where('school_id', $faculty->school_id)->count())->toBeGreaterThanOrEqual(4);

    $response = $this->post(route('login.store'), [
        'email' => 'staff1@futo.edu',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($teacher);
});
