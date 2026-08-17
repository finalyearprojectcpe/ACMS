<?php

namespace App\Actions;

use App\AccountType;
use App\Models\EmailDelivery;
use App\Models\Faculty;
use App\Models\FacultyHeadTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateFacultyHeadTransferAction
{
    /**
     * @return array{transfer: FacultyHeadTransfer, token: string, emailDelivery: EmailDelivery}
     */
    public function execute(User $facultyHead, string $recipientEmail, ?string $recipientName = null): array
    {
        $facultyHead->loadMissing('staffProfile.headedFaculty');
        $faculty = $facultyHead->staffProfile?->headedFaculty;

        if (! $faculty instanceof Faculty) {
            throw ValidationException::withMessages([
                'email' => 'Only the current faculty head can send a faculty head transfer invite.',
            ]);
        }

        $recipient = User::query()
            ->with('staffProfile.department')
            ->where('school_id', $facultyHead->school_id)
            ->where('account_type', AccountType::Staff)
            ->where('email', $recipientEmail)
            ->first();

        if (! $recipient instanceof User || ! $recipient->staffProfile) {
            throw ValidationException::withMessages([
                'email' => 'Enter the email address of a staff member in your faculty.',
            ]);
        }

        if ((int) $recipient->staffProfile->department?->faculty_id !== (int) $faculty->id) {
            throw ValidationException::withMessages([
                'email' => 'Choose a staff member who belongs to your faculty.',
            ]);
        }

        $hasPendingTransfer = FacultyHeadTransfer::query()
            ->where('faculty_id', $faculty->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasPendingTransfer) {
            throw ValidationException::withMessages([
                'email' => 'Cancel the pending faculty head invite before sending another one.',
            ]);
        }

        $token = Str::random(64);
        $accessCode = Str::upper(Str::random(10));
        $inviteUrl = route('faculty-head-transfer.create', [
            'token' => $token,
        ]);

        [$transfer, $emailDelivery] = DB::transaction(function () use ($accessCode, $faculty, $facultyHead, $inviteUrl, $recipient, $recipientEmail, $recipientName, $token): array {
            FacultyHeadTransfer::query()
                ->where('faculty_id', $faculty->id)
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => 'expired',
                    'cancelled_at' => now(),
                ]);

            $transfer = FacultyHeadTransfer::query()->create([
                'school_id' => $faculty->school_id,
                'faculty_id' => $faculty->id,
                'initiated_by_user_id' => $facultyHead->id,
                'recipient_user_id' => $recipient->id,
                'recipient_staff_profile_id' => $recipient->staffProfile->id,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName ?: $recipient->name,
                'access_code' => $accessCode,
                'token_hash' => hash('sha256', $token),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ]);

            $faculty->requestHeadTransfer($recipient->staffProfile);

            $emailDelivery = EmailDelivery::query()->create([
                'school_id' => $faculty->school_id,
                'user_id' => $recipient->id,
                'recipient_email' => $recipientEmail,
                'subject' => 'GradeManager faculty head transfer invite',
                'mailer' => 'resend',
                'status' => 'queued',
                'metadata' => [
                    'access_code' => $accessCode,
                    'invite_url' => $inviteUrl,
                    'faculty_head_transfer_id' => $transfer->id,
                    'faculty_id' => $faculty->id,
                    'initiated_by_user_id' => $facultyHead->id,
                ],
            ]);

            return [$transfer, $emailDelivery];
        });

        return [
            'transfer' => $transfer,
            'token' => $token,
            'emailDelivery' => $emailDelivery,
        ];
    }
}
