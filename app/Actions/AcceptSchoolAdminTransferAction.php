<?php

namespace App\Actions;

use App\AccountStatus;
use App\AccountType;
use App\Models\School;
use App\Models\SchoolAdminTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptSchoolAdminTransferAction
{
    /**
     * @param  array{name: string, access_code: string, password: string}  $validated
     */
    public function execute(SchoolAdminTransfer $transfer, array $validated): User
    {
        return DB::transaction(function () use ($transfer, $validated): User {
            $lockedTransfer = SchoolAdminTransfer::query()
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureTransferCanBeAccepted($lockedTransfer, $validated['access_code']);

            $recipient = User::query()
                ->where('school_id', $lockedTransfer->school_id)
                ->where('email', $lockedTransfer->recipient_email)
                ->lockForUpdate()
                ->first();

            if ($recipient instanceof User) {
                throw ValidationException::withMessages([
                    'access_code' => 'This invite email is already attached to another account in this school. Ask the current school admin to cancel this invite and use another email.',
                ]);
            }

            $recipient = User::query()->create([
                'school_id' => $lockedTransfer->school_id,
                'account_type' => AccountType::SchoolAdmin,
                'name' => $validated['name'],
                'email' => $lockedTransfer->recipient_email,
                'password' => $validated['password'],
                'access_code' => null,
                'access_code_used_at' => null,
                'activated_at' => now(),
                'disabled_at' => null,
                'status' => AccountStatus::Active,
            ]);

            $lockedTransfer->forceFill([
                'accepted_by_user_id' => $recipient->id,
                'status' => 'accepted',
                'accepted_at' => now(),
            ])->save();

            SchoolAdminTransfer::query()
                ->where('school_id', $lockedTransfer->school_id)
                ->where('status', 'pending')
                ->whereKeyNot($lockedTransfer->id)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            $school = School::query()
                ->whereKey($lockedTransfer->school_id)
                ->lockForUpdate()
                ->firstOrFail();
            $previousAdmins = User::query()
                ->where('school_id', $lockedTransfer->school_id)
                ->where('account_type', AccountType::SchoolAdmin)
                ->whereKeyNot($recipient->id)
                ->lockForUpdate()
                ->get();

            foreach ($previousAdmins as $previousAdmin) {
                $previousAdmin->forceFill([
                    'activated_at' => null,
                    'disabled_at' => now(),
                    'status' => AccountStatus::Disabled,
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table('sessions')
                    ->where('user_id', $previousAdmin->id)
                    ->delete();
            }

            $school->forceFill(['administrator_user_id' => $recipient->id])->save();

            return $recipient->refresh();
        });
    }

    private function ensureTransferCanBeAccepted(SchoolAdminTransfer $transfer, string $accessCode): void
    {
        if (! $transfer->isPending() || $transfer->isExpired()) {
            throw ValidationException::withMessages([
                'access_code' => 'This ownership invite is no longer active. Ask the current school admin for a new invite.',
            ]);
        }

        if (! hash_equals($transfer->access_code, $accessCode)) {
            throw ValidationException::withMessages([
                'access_code' => 'The access code does not match this ownership invite.',
            ]);
        }
    }
}
