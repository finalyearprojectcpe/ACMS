<?php

namespace App\Actions;

use App\Models\EmailDelivery;
use App\Models\SchoolAdminTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSchoolAdminTransferAction
{
    /**
     * @return array{transfer: SchoolAdminTransfer, token: string, emailDelivery: EmailDelivery}
     */
    public function execute(User $schoolAdmin, string $recipientEmail, ?string $recipientName = null): array
    {
        $hasPendingTransfer = SchoolAdminTransfer::query()
            ->where('school_id', $schoolAdmin->school_id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasPendingTransfer) {
            throw ValidationException::withMessages([
                'email' => 'Cancel the pending ownership invite before sending another one.',
            ]);
        }

        $token = Str::random(64);
        $accessCode = Str::upper(Str::random(10));
        $inviteUrl = route('ownership-transfer.create', [
            'token' => $token,
        ]);

        [$transfer, $emailDelivery] = DB::transaction(function () use ($accessCode, $inviteUrl, $recipientEmail, $recipientName, $schoolAdmin, $token): array {
            SchoolAdminTransfer::query()
                ->where('school_id', $schoolAdmin->school_id)
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => 'expired',
                    'cancelled_at' => now(),
                ]);

            $transfer = SchoolAdminTransfer::query()->create([
                'school_id' => $schoolAdmin->school_id,
                'initiated_by_user_id' => $schoolAdmin->id,
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'access_code' => $accessCode,
                'token_hash' => hash('sha256', $token),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ]);

            $emailDelivery = EmailDelivery::query()->create([
                'school_id' => $schoolAdmin->school_id,
                'user_id' => null,
                'recipient_email' => $recipientEmail,
                'subject' => 'GradeManager ownership transfer invite',
                'mailer' => 'cloudflare',
                'status' => 'queued',
                'metadata' => [
                    'access_code' => $accessCode,
                    'invite_url' => $inviteUrl,
                    'recipient_name' => $recipientName,
                    'school_admin_transfer_id' => $transfer->id,
                    'initiated_by_user_id' => $schoolAdmin->id,
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
