<?php

namespace App\Services;

use App\Jobs\GenerateDuePaymentReceipt;
use App\Models\DuePayment;
use App\Models\EmailDelivery;
use Illuminate\Support\Facades\DB;

class DueReceiptService
{
    public function __construct(
        private readonly EmailDeliveryService $emailDelivery,
        private readonly ReceiptUrlService $receiptUrls,
    ) {}

    public function queue(DuePayment $payment, bool $force = false): DuePayment
    {
        $shouldDispatch = false;
        $payment = DB::transaction(function () use ($payment, $force, &$shouldDispatch): DuePayment {
            $payment = DuePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($payment->status, [DuePayment::StatusSuccess, DuePayment::StatusReversed], true)) {
                return $payment;
            }

            if (! is_string($payment->paystack_transaction_id) || $payment->paystack_transaction_id === '') {
                return $payment;
            }

            if (! $force && in_array($payment->receipt_status, [
                DuePayment::ReceiptStatusPending,
                DuePayment::ReceiptStatusProcessing,
                DuePayment::ReceiptStatusReady,
            ], true)) {
                return $payment;
            }

            $payment->forceFill([
                'receipt_status' => DuePayment::ReceiptStatusPending,
                'receipt_generation_error' => null,
            ])->save();
            $shouldDispatch = true;

            return $payment;
        });

        if ($shouldDispatch) {
            GenerateDuePaymentReceipt::dispatch($payment->id)->afterCommit();
        }

        return $payment->refresh();
    }

    public function queueEmail(DuePayment $payment, bool $newSendCycle = false): ?EmailDelivery
    {
        $payment->loadMissing(['assignment.due', 'studentProfile.user']);
        $recipient = $payment->studentProfile->user->email;

        if (! is_string($recipient) || $recipient === '' || $payment->receipt_path === null) {
            return null;
        }

        $delivery = EmailDelivery::query()->firstOrCreate(
            ['purpose_key' => 'due-receipt-'.$payment->id],
            [
                'advisor_profile_id' => $payment->advisor_profile_id,
                'user_id' => $payment->studentProfile->user_id,
                'recipient_email' => $recipient,
                'subject' => 'Payment Receipt - '.$payment->assignment->due->title,
                'mailer' => 'resend',
                'status' => 'queued',
            ],
        );

        $delivery->forceFill([
            'recipient_email' => $recipient,
            'subject' => 'Payment Receipt - '.$payment->assignment->due->title,
        ])->save();
        $payment->forceFill(['receipt_email_delivery_id' => $delivery->id])->save();

        return $this->emailDelivery->sendDuePaymentReceipt(
            $delivery,
            dueName: $payment->assignment->due->title,
            amount: sprintf('NGN %s', number_format($payment->amount_kobo / 100, 2)),
            receiptNumber: (string) $payment->receipt_number,
            downloadUrl: $this->receiptUrls->temporaryDownloadUrl($payment),
            disk: (string) $payment->receipt_disk,
            path: $payment->receipt_path,
            newSendCycle: $newSendCycle,
        );
    }
}
