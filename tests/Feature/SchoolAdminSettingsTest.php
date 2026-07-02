<?php

use App\AccountType;
use App\Models\EmailDelivery;
use App\Models\School;
use App\Models\SchoolAdminTransfer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

test('school admins can change their password', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
        'password' => 'old-password',
    ]);

    $response = $this->actingAs($schoolAdmin)->patch(route('dashboard.settings.password.update'), [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'settings',
    ]));
    $response->assertSessionHas('success', 'Password changed.');

    expect(Hash::check('new-password', $schoolAdmin->refresh()->password))->toBeTrue();
});

test('school admins can send an ownership transfer invite with a visible access code', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
        'email' => 'schooladmin@futo.edu',
    ]);

    $response = $this->actingAs($schoolAdmin)->post(route('dashboard.settings.ownership-transfer.store'), [
        'email' => 'newadmin@futo.edu',
        'name' => 'Okafor Ada',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'settings',
    ]));
    $response->assertSessionHas('success', 'Ownership invite sent.');

    $transfer = SchoolAdminTransfer::query()->firstOrFail();
    $emailDelivery = EmailDelivery::query()->where('recipient_email', 'newadmin@futo.edu')->firstOrFail();

    expect($transfer->school_id)->toBe($school->id)
        ->and($transfer->recipient_email)->toBe('newadmin@futo.edu')
        ->and($transfer->recipient_name)->toBe('Okafor Ada')
        ->and($transfer->status)->toBe('pending')
        ->and($transfer->access_code)->not->toBeNull()
        ->and($emailDelivery->status)->toBe('skipped')
        ->and($emailDelivery->metadata['access_code'])->toBe($transfer->access_code)
        ->and($emailDelivery->metadata['invite_url'])->toContain('/ownership-transfer/');
});

test('school admins cannot stack active ownership transfer invites', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
        'email' => 'schooladmin@futo.edu',
    ]);

    SchoolAdminTransfer::factory()->create([
        'school_id' => $school->id,
        'initiated_by_user_id' => $schoolAdmin->id,
        'recipient_email' => 'pending@futo.edu',
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($schoolAdmin)->post(route('dashboard.settings.ownership-transfer.store'), [
        'email' => 'another@futo.edu',
        'name' => 'Nwosu Kene',
    ]);

    $response->assertSessionHasErrors('email');

    expect(SchoolAdminTransfer::query()->where('school_id', $school->id)->count())->toBe(1);
});

test('school admins must use a fresh school email for ownership transfer invites', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
        'email' => 'schooladmin@futo.edu',
    ]);
    User::factory()->staff()->create([
        'school_id' => $school->id,
        'email' => 'staff@futo.edu',
    ]);

    $response = $this->actingAs($schoolAdmin)->post(route('dashboard.settings.ownership-transfer.store'), [
        'email' => 'staff@futo.edu',
        'name' => 'Nwosu Kene',
    ]);

    $response->assertSessionHasErrors('email');

    expect(SchoolAdminTransfer::query()->where('school_id', $school->id)->count())->toBe(0);
});

test('school admins can cancel a pending ownership transfer invite', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
    ]);
    $transfer = SchoolAdminTransfer::factory()->create([
        'school_id' => $school->id,
        'initiated_by_user_id' => $schoolAdmin->id,
    ]);

    $response = $this->actingAs($schoolAdmin)->delete(route('dashboard.settings.ownership-transfer.destroy', [
        'schoolAdminTransfer' => $transfer->id,
    ]));

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'settings',
    ]));
    $response->assertSessionHas('success', 'Ownership invite cancelled.');

    expect($transfer->refresh()->status)->toBe('cancelled')
        ->and($transfer->cancelled_at)->not->toBeNull();
});

test('recipients can accept an ownership transfer and deactivate the previous school admin', function () {
    $school = School::factory()->create();
    $previousAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
        'email' => 'schooladmin@futo.edu',
        'activated_at' => now(),
    ]);
    $token = Str::random(64);
    $transfer = SchoolAdminTransfer::factory()->create([
        'school_id' => $school->id,
        'initiated_by_user_id' => $previousAdmin->id,
        'recipient_email' => 'incoming@futo.edu',
        'recipient_name' => 'Eze Ifeoma',
        'access_code' => 'FUTOADMIN1',
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->post(route('ownership-transfer.store', [
        'token' => $token,
    ]), [
        'name' => 'Eze Ifeoma',
        'access_code' => 'FUTOADMIN1',
        'password' => 'new-admin-password',
        'password_confirmation' => 'new-admin-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Ownership transferred. You are now the school admin.');

    $newAdmin = User::query()
        ->where('school_id', $school->id)
        ->where('email', 'incoming@futo.edu')
        ->firstOrFail();

    $this->assertAuthenticatedAs($newAdmin);

    expect($newAdmin->account_type)->toBe(AccountType::SchoolAdmin)
        ->and($newAdmin->activated_at)->not->toBeNull()
        ->and(Hash::check('new-admin-password', $newAdmin->password))->toBeTrue()
        ->and($previousAdmin->refresh()->activated_at)->toBeNull()
        ->and($transfer->refresh()->status)->toBe('accepted')
        ->and($transfer->accepted_by_user_id)->toBe($newAdmin->id);
});
