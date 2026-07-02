<?php

namespace App\Actions;

use App\Models\Faculty;
use App\Models\FacultyHeadTransfer;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptFacultyHeadTransferAction
{
    /**
     * @param  array{name: string, access_code: string, password: string}  $validated
     */
    public function execute(FacultyHeadTransfer $transfer, array $validated): User
    {
        return DB::transaction(function () use ($transfer, $validated): User {
            $lockedTransfer = FacultyHeadTransfer::query()
                ->whereKey($transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureTransferCanBeAccepted($lockedTransfer, $validated['access_code']);

            $faculty = Faculty::query()
                ->whereKey($lockedTransfer->faculty_id)
                ->lockForUpdate()
                ->firstOrFail();
            $recipient = User::query()
                ->whereKey($lockedTransfer->recipient_user_id)
                ->lockForUpdate()
                ->firstOrFail();
            $staffProfile = StaffProfile::query()
                ->with('department')
                ->whereKey($lockedTransfer->recipient_staff_profile_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $staffProfile->department?->faculty_id !== (int) $faculty->id) {
                throw ValidationException::withMessages([
                    'access_code' => 'This invite no longer matches a staff member in this faculty. Ask the current faculty head for a new invite.',
                ]);
            }

            $recipient->forceFill([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'access_code' => null,
                'access_code_used_at' => now(),
                'activated_at' => now(),
            ])->save();

            $faculty->forceFill([
                'head_staff_profile_id' => $staffProfile->id,
                'pending_head_staff_profile_id' => null,
                'head_transfer_requested_at' => null,
            ])->save();

            $lockedTransfer->forceFill([
                'accepted_by_user_id' => $recipient->id,
                'status' => 'accepted',
                'accepted_at' => now(),
            ])->save();

            FacultyHeadTransfer::query()
                ->where('faculty_id', $faculty->id)
                ->where('status', 'pending')
                ->whereKeyNot($lockedTransfer->id)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            return $recipient->refresh();
        });
    }

    private function ensureTransferCanBeAccepted(FacultyHeadTransfer $transfer, string $accessCode): void
    {
        if (! $transfer->isPending() || $transfer->isExpired()) {
            throw ValidationException::withMessages([
                'access_code' => 'This faculty head invite is no longer active. Ask the current faculty head for a new invite.',
            ]);
        }

        if (! hash_equals($transfer->access_code, $accessCode)) {
            throw ValidationException::withMessages([
                'access_code' => 'The access code does not match this faculty head invite.',
            ]);
        }
    }
}
