<?php

use App\Models\Department;
use App\Models\EmailDelivery;
use App\Models\Faculty;
use App\Models\FacultyHeadTransfer;
use App\Models\School;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function facultyHeadSettingsContext(): array
{
    $school = School::factory()->create();
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'name' => 'School of Engineering and Engineering Technology',
        'abbreviation' => 'SESET',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $headUser = User::factory()->staff()->create([
        'school_id' => $school->id,
        'name' => 'Okafor Ada',
        'email' => 'facultyhead@futo.edu',
        'password' => 'old-password',
        'activated_at' => now(),
    ]);
    $headProfile = StaffProfile::factory()->create([
        'user_id' => $headUser->id,
        'department_id' => $department->id,
    ]);
    $recipientUser = User::factory()->staff()->create([
        'school_id' => $school->id,
        'name' => 'Nwosu Kene',
        'email' => 'incominghead@futo.edu',
        'activated_at' => now(),
    ]);
    $recipientProfile = StaffProfile::factory()->create([
        'user_id' => $recipientUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($headProfile);

    return [
        'school' => $school,
        'faculty' => $faculty->refresh(),
        'department' => $department,
        'headUser' => $headUser,
        'headProfile' => $headProfile,
        'recipientUser' => $recipientUser,
        'recipientProfile' => $recipientProfile,
    ];
}

test('faculty heads can change their password', function () {
    $context = facultyHeadSettingsContext();

    $response = $this->actingAs($context['headUser'])->patch(route('dashboard.settings.faculty-head-password.update'), [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'settings',
    ]));
    $response->assertSessionHas('success', 'Password changed.');

    expect(Hash::check('new-password', $context['headUser']->refresh()->password))->toBeTrue();
});

test('users can upload a profile photo', function () {
    config(['filesystems.profile_photo_disk' => 'public']);
    Storage::fake(User::profilePhotoDisk());
    $context = facultyHeadSettingsContext();

    $response = $this
        ->actingAs($context['headUser'])
        ->from(route('dashboard.section', ['section' => 'settings']))
        ->patch(route('dashboard.settings.profile-photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'settings',
    ]));
    $response->assertSessionHas('success', 'Profile photo updated.');

    $context['headUser']->refresh();

    expect($context['headUser']->profile_photo_path)->not->toBeNull()
        ->and($context['headUser']->profilePhotoUrl())->toContain('/storage/profile-photos/');

    Storage::disk(User::profilePhotoDisk())->assertExists($context['headUser']->profile_photo_path);
});

test('profile photos can be stored on the configured r2 disk', function () {
    config(['filesystems.profile_photo_disk' => 'r2']);
    Storage::fake('r2');
    $context = facultyHeadSettingsContext();

    $response = $this
        ->actingAs($context['headUser'])
        ->from(route('dashboard.section', ['section' => 'settings']))
        ->patch(route('dashboard.settings.profile-photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.webp', 300, 300),
        ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'settings',
    ]));
    $response->assertSessionHas('success', 'Profile photo updated.');

    $context['headUser']->refresh();

    expect(User::profilePhotoDisk())->toBe('r2')
        ->and($context['headUser']->profile_photo_path)->not->toBeNull();

    Storage::disk('r2')->assertExists($context['headUser']->profile_photo_path);
});

test('profile photo urls use the configured r2 public url', function () {
    config([
        'filesystems.profile_photo_disk' => 'r2',
        'filesystems.disks.r2' => [
            'driver' => 's3',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'auto',
            'bucket' => 'grademanager-test',
            'url' => 'https://assets.grademanager.test',
            'endpoint' => 'https://account-id.r2.cloudflarestorage.com',
            'use_path_style_endpoint' => true,
        ],
    ]);

    $user = User::factory()->staff()->create([
        'profile_photo_path' => 'profile-photos/avatar.jpg',
    ]);

    expect($user->profilePhotoUrl())->toBe('https://assets.grademanager.test/profile-photos/avatar.jpg');
});

test('faculty heads can send a faculty head transfer invite with a visible access code', function () {
    $context = facultyHeadSettingsContext();

    $response = $this->actingAs($context['headUser'])->post(route('dashboard.settings.faculty-head-transfer.store'), [
        'email' => 'incominghead@futo.edu',
        'name' => 'Nwosu Kene',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'settings',
    ]));
    $response->assertSessionHas('success', 'Faculty head invite sent.');

    $transfer = FacultyHeadTransfer::query()->firstOrFail();
    $emailDelivery = EmailDelivery::query()->where('recipient_email', 'incominghead@futo.edu')->firstOrFail();

    expect($transfer->faculty_id)->toBe($context['faculty']->id)
        ->and($transfer->recipient_user_id)->toBe($context['recipientUser']->id)
        ->and($transfer->recipient_staff_profile_id)->toBe($context['recipientProfile']->id)
        ->and($transfer->status)->toBe('pending')
        ->and($transfer->access_code)->not->toBeNull()
        ->and($context['faculty']->refresh()->pending_head_staff_profile_id)->toBe($context['recipientProfile']->id)
        ->and($emailDelivery->status)->toBe('skipped')
        ->and($emailDelivery->metadata['access_code'])->toBe($transfer->access_code)
        ->and($emailDelivery->metadata['invite_url'])->toContain('/faculty-head-transfer/');
});

test('faculty heads can cancel a pending faculty head transfer invite', function () {
    $context = facultyHeadSettingsContext();

    $this->actingAs($context['headUser'])->post(route('dashboard.settings.faculty-head-transfer.store'), [
        'email' => 'incominghead@futo.edu',
        'name' => 'Nwosu Kene',
    ]);

    $transfer = FacultyHeadTransfer::query()->firstOrFail();

    $response = $this->actingAs($context['headUser'])->delete(route('dashboard.settings.faculty-head-transfer.destroy', [
        'facultyHeadTransfer' => $transfer->id,
    ]));

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'settings',
    ]));
    $response->assertSessionHas('success', 'Faculty head invite cancelled.');

    expect($transfer->refresh()->status)->toBe('cancelled')
        ->and($transfer->cancelled_at)->not->toBeNull()
        ->and($context['faculty']->refresh()->pending_head_staff_profile_id)->toBeNull();
});

test('recipients can accept a faculty head transfer without disabling the previous staff account', function () {
    $context = facultyHeadSettingsContext();
    $token = Str::random(64);
    $transfer = FacultyHeadTransfer::factory()->create([
        'school_id' => $context['school']->id,
        'faculty_id' => $context['faculty']->id,
        'initiated_by_user_id' => $context['headUser']->id,
        'recipient_user_id' => $context['recipientUser']->id,
        'recipient_staff_profile_id' => $context['recipientProfile']->id,
        'recipient_email' => 'incominghead@futo.edu',
        'recipient_name' => 'Nwosu Kene',
        'access_code' => 'SESETHEAD1',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ]);
    $context['faculty']->requestHeadTransfer($context['recipientProfile']);

    $response = $this->post(route('faculty-head-transfer.store', [
        'token' => $token,
    ]), [
        'name' => 'Nwosu Kene',
        'access_code' => 'SESETHEAD1',
        'password' => 'new-head-password',
        'password_confirmation' => 'new-head-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Faculty head access transferred. You are now the faculty head.');

    $this->assertAuthenticatedAs($context['recipientUser']->refresh());

    expect($context['faculty']->refresh()->head_staff_profile_id)->toBe($context['recipientProfile']->id)
        ->and($context['faculty']->pending_head_staff_profile_id)->toBeNull()
        ->and($context['headUser']->refresh()->activated_at)->not->toBeNull()
        ->and(Hash::check('new-head-password', $context['recipientUser']->refresh()->password))->toBeTrue()
        ->and($transfer->refresh()->status)->toBe('accepted')
        ->and($transfer->accepted_by_user_id)->toBe($context['recipientUser']->id);
});
