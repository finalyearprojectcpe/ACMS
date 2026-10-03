<?php

use App\Mail\PasswordResetMail;
use App\Models\AdvisorProfile;
use App\Models\EmailDelivery;
use App\Models\User;
use App\Services\EmailDeliveryService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

test('active users can reset their password with a single-use link', function () {
    $user = User::factory()->adviser()->create([
        'email' => 'teacher@example.edu',
        'password' => 'old-password',
    ]);
    AdvisorProfile::factory()->create(['user_id' => $user->id]);

    $this->post(route('password.email'), ['email' => 'teacher@example.edu'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $delivery = EmailDelivery::query()->where('user_id', $user->id)->firstOrFail();
    $resetUrl = $delivery->metadata['reset_url'];
    $token = Str::afterLast((string) parse_url($resetUrl, PHP_URL_PATH), '/');

    expect(DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->exists())->toBeTrue()
        ->and((new PasswordResetMail($delivery))->render())
        ->toContain('your ACMS account');

    $this->get($resetUrl)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reset-password')
            ->where('email', $user->email)
            ->where('token', $token));

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'reset12',
        'password_confirmation' => 'reset12',
    ])->assertRedirect(route('home'));

    expect(Hash::check('reset12', $user->refresh()->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->exists())->toBeFalse();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'reset34',
        'password_confirmation' => 'reset34',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('reset12', $user->refresh()->password))->toBeTrue();
});

test('password reset requests do not reveal whether an email exists', function () {
    $this->post(route('password.email'), ['email' => 'missing@example.edu'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(EmailDelivery::query()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('an expired reset token cannot change the password', function () {
    $user = User::factory()->create([
        'email' => 'teacher@example.edu',
        'password' => 'old-password',
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    $delivery = EmailDelivery::query()->where('user_id', $user->id)->firstOrFail();
    $resetUrl = $delivery->metadata['reset_url'];
    $token = Str::afterLast((string) parse_url($resetUrl, PHP_URL_PATH), '/');
    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subHours(2)]);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'reset12',
        'password_confirmation' => 'reset12',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('old-password', $user->refresh()->password))->toBeTrue();
});

test('email delivery respects the configured local mailer without Resend credentials', function () {
    Config::set('mail.default', 'log');
    Config::set('services.resend.key', null);
    Mail::fake();
    Queue::fake();
    $delivery = EmailDelivery::factory()->create([
        'mailer' => 'resend',
        'metadata' => ['template' => 'password_reset', 'reset_url' => 'https://example.test/reset'],
    ]);

    app(EmailDeliveryService::class)->sendPasswordResetInvitation($delivery, 'https://example.test/reset');
    app(EmailDeliveryService::class)->deliver($delivery);

    expect($delivery->refresh()->mailer)->toBe('log')
        ->and($delivery->status)->toBe('sent');
    Mail::assertSent(PasswordResetMail::class);
});
