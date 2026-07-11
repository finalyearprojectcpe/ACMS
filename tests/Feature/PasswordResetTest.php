<?php

use App\Models\EmailDelivery;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

test('active users can reset their password with a school-scoped single-use link', function () {
    $school = School::factory()->create(['abbreviation' => 'FUTO']);
    Config::set('app.default_school_abbreviation', $school->abbreviation);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'email' => 'teacher@futo.edu',
        'password' => 'old-password',
    ]);

    $this->post(route('password.email'), ['email' => 'teacher@futo.edu'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $delivery = EmailDelivery::query()->where('user_id', $user->id)->firstOrFail();
    $resetUrl = $delivery->metadata['reset_url'];
    $token = Str::afterLast((string) parse_url($resetUrl, PHP_URL_PATH), '/');

    expect(DB::table('password_reset_tokens')
        ->where('school_id', $school->id)
        ->where('email', $user->email)
        ->exists())->toBeTrue();

    $this->get($resetUrl)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reset-password')
            ->where('email', $user->email)
            ->where('token', $token));

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertRedirect(route('home'));

    expect(Hash::check('new-secure-password', $user->refresh()->password))->toBeTrue()
        ->and(DB::table('password_reset_tokens')
            ->where('school_id', $school->id)
            ->where('email', $user->email)
            ->exists())->toBeFalse();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'another-password',
        'password_confirmation' => 'another-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('new-secure-password', $user->refresh()->password))->toBeTrue();
});

test('password reset requests do not reveal whether an email exists', function () {
    $school = School::factory()->create(['abbreviation' => 'FUTO']);
    Config::set('app.default_school_abbreviation', $school->abbreviation);

    $this->post(route('password.email'), ['email' => 'missing@futo.edu'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(EmailDelivery::query()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('password reset links keep the school subdomain that requested them', function () {
    $school = School::factory()->create(['abbreviation' => 'MIT']);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'email' => 'teacher@mit.edu',
    ]);
    Config::set('app.school_domain', 'site.edu');

    $this->post('http://mit.site.edu/forgot-password', ['email' => $user->email])
        ->assertRedirect();

    $resetUrl = EmailDelivery::query()
        ->where('user_id', $user->id)
        ->firstOrFail()
        ->metadata['reset_url'];

    expect(parse_url((string) $resetUrl, PHP_URL_HOST))->toBe('mit.site.edu');
});

test('a reset link issued by one school cannot change the same email at another school', function () {
    $firstSchool = School::factory()->create(['abbreviation' => 'FUTO']);
    $secondSchool = School::factory()->create(['abbreviation' => 'MIT']);
    $firstUser = User::factory()->create([
        'school_id' => $firstSchool->id,
        'email' => 'shared@example.edu',
        'password' => 'first-password',
    ]);
    $secondUser = User::factory()->create([
        'school_id' => $secondSchool->id,
        'email' => 'shared@example.edu',
        'password' => 'second-password',
    ]);
    Config::set('app.default_school_abbreviation', $firstSchool->abbreviation);

    $this->post(route('password.email'), ['email' => $firstUser->email]);

    $resetUrl = EmailDelivery::query()->where('user_id', $firstUser->id)->firstOrFail()->metadata['reset_url'];
    $token = Str::afterLast((string) parse_url($resetUrl, PHP_URL_PATH), '/');
    Config::set('app.default_school_abbreviation', $secondSchool->abbreviation);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $secondUser->email,
        'password' => 'cross-school-password',
        'password_confirmation' => 'cross-school-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('first-password', $firstUser->refresh()->password))->toBeTrue()
        ->and(Hash::check('second-password', $secondUser->refresh()->password))->toBeTrue();
});
