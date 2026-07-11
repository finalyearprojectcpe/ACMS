<?php

namespace App\Services;

use App\AccountStatus;
use App\Models\EmailDelivery;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(private readonly CloudflareEmailService $emailService) {}

    public function requestReset(School $school, string $email): void
    {
        $user = $this->eligibleUser($school, $email);

        if (! $user instanceof User) {
            return;
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            [
                'school_id' => $school->id,
                'email' => $email,
            ],
            [
                'token' => hash('sha256', $token),
                'created_at' => now(),
            ],
        );

        $delivery = EmailDelivery::query()->create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'recipient_email' => $email,
            'subject' => 'Reset your GradeManager password',
            'mailer' => 'cloudflare',
            'status' => 'queued',
            'metadata' => [],
        ]);
        $this->emailService->sendPasswordResetInvitation(
            $delivery,
            route('password.reset', [
                'token' => $token,
                'email' => $email,
            ]),
        );
    }

    public function reset(School $school, string $email, string $token, string $password): bool
    {
        return DB::transaction(function () use ($school, $email, $password, $token): bool {
            $reset = DB::table('password_reset_tokens')
                ->where('school_id', $school->id)
                ->where('email', $email)
                ->lockForUpdate()
                ->first();
            $createdAt = is_string($reset?->created_at)
                ? Carbon::parse($reset->created_at)
                : null;
            $expiresAfterMinutes = (int) config('auth.passwords.users.expire', 60);

            if (
                $reset === null
                || ! is_string($reset->token)
                || ! hash_equals($reset->token, hash('sha256', $token))
                || $createdAt === null
                || $createdAt->addMinutes($expiresAfterMinutes)->isPast()
            ) {
                return false;
            }

            $user = $this->eligibleUser($school, $email);

            if (! $user instanceof User) {
                return false;
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')
                ->where('school_id', $school->id)
                ->where('email', $email)
                ->delete();

            return true;
        });
    }

    private function eligibleUser(School $school, string $email): ?User
    {
        return User::query()
            ->where('school_id', $school->id)
            ->where('email', $email)
            ->where('status', AccountStatus::Active->value)
            ->whereNotNull('activated_at')
            ->whereNull('disabled_at')
            ->first();
    }
}
