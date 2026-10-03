<?php

use App\Honorific;
use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('an adviser can open the focused profile page', function () {
    $advisor = AdvisorProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->get(route('dashboard.section', ['section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.section', 'settings')
            ->where('dashboard.sectionTitle', 'Settings')
            ->where('dashboard.adviserProfile.adviser.name', $advisor->user->name)
            ->has('dashboard.adviserProfile.honorifics', 5)
            ->has('dashboard.adviserPaymentSettings.accounts', 0)
            ->where('dashboard.adviserStudentSets', null));
});

test('an adviser can update their honorific and name', function () {
    $advisor = AdvisorProfile::factory()->create();

    $this->actingAs($advisor->user)
        ->patch(route('dashboard.profile.update'), [
            'title' => Honorific::Prof->value,
            'name' => '  Chinedu   Okafor  ',
        ])
        ->assertRedirect(route('dashboard.section', ['section' => 'settings']));

    expect($advisor->user->refresh()->title)->toBe(Honorific::Prof)
        ->and($advisor->user->name)->toBe('Chinedu Okafor');
});

test('an adviser can save their details photo and password together', function () {
    $advisor = AdvisorProfile::factory()->create();
    $disk = User::profilePhotoDisk();

    Storage::fake($disk);
    Storage::disk($disk)->put('profile-photos/previous.jpg', 'previous');
    $advisor->user->forceFill([
        'profile_photo_path' => 'profile-photos/previous.jpg',
    ])->save();

    $this->actingAs($advisor->user)
        ->post(route('dashboard.profile.update'), [
            '_method' => 'patch',
            'title' => Honorific::Dr->value,
            'name' => 'Chika Nwosu',
            'profile_photo' => UploadedFile::fake()->image('profile.png'),
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect(route('dashboard.section', ['section' => 'settings']));

    $user = $advisor->user->refresh();

    expect($user->title)->toBe(Honorific::Dr)
        ->and($user->name)->toBe('Chika Nwosu')
        ->and(Hash::check('NewPassword123!', $user->password))->toBeTrue()
        ->and($user->profile_photo_path)->not->toBeNull();

    Storage::disk($disk)->assertExists((string) $user->profile_photo_path);
    Storage::disk($disk)->assertMissing('profile-photos/previous.jpg');

    $photoResponse = $this->get($user->profilePhotoUrl());
    $photoResponse->assertOk();
    expect($photoResponse->streamedContent())->not->toBeEmpty();
});

test('profile photos are available to the adviser and their student but not unrelated accounts', function () {
    $advisor = AdvisorProfile::factory()->create();
    $student = StudentProfile::factory()->create(['advisor_profile_id' => $advisor->id]);
    $outsider = AdvisorProfile::factory()->create();
    Storage::fake(User::profilePhotoDisk());
    Storage::disk(User::profilePhotoDisk())->put('profile-photos/student.jpg', 'image-bytes');
    $student->user->forceFill(['profile_photo_path' => 'profile-photos/student.jpg'])->save();

    $url = $student->user->profilePhotoUrl();
    $this->actingAs($advisor->user)->get($url)->assertOk();
    $this->actingAs($outsider->user)->get($url)->assertNotFound();
});

test('an adviser must confirm their current password before changing it', function () {
    $advisor = AdvisorProfile::factory()->create();
    $user = $advisor->user;
    $user->forceFill(['name' => 'Original Name'])->save();

    $this->actingAs($user)
        ->patch(route('dashboard.profile.update'), [
            'title' => Honorific::Prof->value,
            'name' => 'Changed Name',
            'current_password' => 'incorrect-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertSessionHasErrors('current_password');

    expect($user->refresh()->name)->toBe('Original Name')
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

test('a student cannot update an adviser profile', function () {
    $student = StudentProfile::factory()->create();

    $this->actingAs($student->user)
        ->patch(route('dashboard.profile.update'), [
            'title' => Honorific::Dr->value,
            'name' => 'Adaeze Okoro',
        ])
        ->assertForbidden();
});

test('a student can save their photo and password without changing their official name', function () {
    $student = StudentProfile::factory()->create();
    $disk = User::profilePhotoDisk();

    Storage::fake($disk);
    Storage::disk($disk)->put('profile-photos/previous.jpg', 'previous');
    $student->user->forceFill([
        'name' => $student->official_name,
        'profile_photo_path' => 'profile-photos/previous.jpg',
    ])->save();

    $this->actingAs($student->user)
        ->post(route('dashboard.profile-settings.update'), [
            '_method' => 'patch',
            'surname' => 'Nwosu',
            'other_names' => 'Adaeze Chioma',
            'profile_photo' => UploadedFile::fake()->image('student.png'),
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect(route('dashboard.section', ['section' => 'profile-settings']));

    $user = $student->user->refresh();

    expect($user->name)->toBe($student->official_name)
        ->and(Hash::check('NewPassword123!', $user->password))->toBeTrue()
        ->and($user->profile_photo_path)->not->toBeNull();

    Storage::disk($disk)->assertExists((string) $user->profile_photo_path);
    Storage::disk($disk)->assertMissing('profile-photos/previous.jpg');
});
