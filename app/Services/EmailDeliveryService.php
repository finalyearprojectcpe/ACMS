<?php

namespace App\Services;

use App\EmailDeliveryStatus;
use App\Jobs\SendEmailDelivery;
use App\Mail\DuePaymentReceiptMail;
use App\Mail\GradeManagerMail;
use App\Mail\PasswordResetMail;
use App\Models\EmailDelivery;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EmailDeliveryService
{
    public function sendPasswordResetInvitation(EmailDelivery $delivery, string $resetUrl): EmailDelivery
    {
        return $this->queue($delivery, [
            'template' => 'password_reset',
            'reset_url' => $resetUrl,
        ]);
    }

    public function sendDuePaymentReceipt(
        EmailDelivery $delivery,
        string $dueName,
        string $amount,
        string $receiptNumber,
        string $downloadUrl,
        string $disk,
        string $path,
        bool $newSendCycle = false,
    ): EmailDelivery {
        return $this->queue($delivery, [
            'template' => 'due_payment_receipt',
            'due_name' => $dueName,
            'amount' => $amount,
            'receipt_number' => $receiptNumber,
            'download_url' => $downloadUrl,
            'attachment' => [
                'disk' => $disk,
                'path' => $path,
                'filename' => 'receipt-'.$receiptNumber.'.pdf',
                'content_type' => 'application/pdf',
            ],
        ], $newSendCycle);
    }

    public function deliver(EmailDelivery $delivery): EmailDelivery
    {
        $delivery->refresh();

        if (in_array($delivery->status, [
            EmailDeliveryStatus::Sent->value,
            EmailDeliveryStatus::Delivered->value,
            EmailDeliveryStatus::Bounced->value,
            EmailDeliveryStatus::Suppressed->value,
            EmailDeliveryStatus::Complained->value,
        ], true)) {
            return $delivery;
        }

        if (! is_string(config('services.resend.key')) || config('services.resend.key') === '') {
            $delivery->forceFill([
                'status' => EmailDeliveryStatus::Skipped->value,
                'error' => 'Resend email delivery is not configured.',
            ])->save();

            return $delivery;
        }

        $delivery->forceFill([
            'status' => EmailDeliveryStatus::Processing->value,
            'attempts' => $delivery->attempts + 1,
            'error' => null,
            'failed_at' => null,
        ])->save();

        try {
            $sentMessage = Mail::mailer('resend')
                ->to($delivery->recipient_email)
                ->send($this->mailableFor($delivery));

            $delivery->refresh();
            $hasProviderOutcome = $delivery->provider_event_at !== null;

            $delivery->forceFill([
                'status' => $hasProviderOutcome
                    ? $delivery->status
                    : EmailDeliveryStatus::Sent->value,
                'provider_message_id' => $this->providerMessageId($sentMessage)
                    ?? $delivery->provider_message_id,
                'sent_at' => $delivery->sent_at ?? now(),
                'error' => $hasProviderOutcome ? $delivery->error : null,
                'failed_at' => $hasProviderOutcome ? $delivery->failed_at : null,
            ])->save();

            return $delivery;
        } catch (Throwable $exception) {
            $delivery->refresh();

            if ($delivery->provider_event_at !== null || in_array($delivery->status, [
                EmailDeliveryStatus::Sent->value,
                EmailDeliveryStatus::Delivered->value,
            ], true)) {
                return $delivery;
            }

            $delivery->forceFill([
                'status' => EmailDeliveryStatus::Failed->value,
                'error' => Str::limit($exception->getMessage(), 2000),
                'failed_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function queue(EmailDelivery $delivery, array $metadata, bool $newSendCycle = false): EmailDelivery
    {
        $delivery->forceFill([
            'mailer' => 'resend',
            'status' => EmailDeliveryStatus::Queued->value,
            'idempotency_key' => $newSendCycle || ! is_string($delivery->idempotency_key) || $delivery->idempotency_key === ''
                ? 'email-delivery/'.Str::uuid()
                : $delivery->idempotency_key,
            'provider_message_id' => $newSendCycle ? null : $delivery->provider_message_id,
            'provider_event_at' => $newSendCycle ? null : $delivery->provider_event_at,
            'sent_at' => $newSendCycle ? null : $delivery->sent_at,
            'delivered_at' => $newSendCycle ? null : $delivery->delivered_at,
            'error' => null,
            'failed_at' => null,
            'metadata' => [
                ...($delivery->metadata ?? []),
                ...$metadata,
            ],
        ])->save();

        SendEmailDelivery::dispatch($delivery->id)
            ->onQueue('emails')
            ->afterCommit();

        return $delivery;
    }

    private function mailableFor(EmailDelivery $delivery): GradeManagerMail
    {
        return match (data_get($delivery->metadata, 'template')) {
            'password_reset' => new PasswordResetMail($delivery),
            'due_payment_receipt' => new DuePaymentReceiptMail($delivery),
            default => throw new RuntimeException('The email type is not supported.'),
        };
    }

    private function providerMessageId(?SentMessage $sentMessage): ?string
    {
        $header = $sentMessage?->getOriginalMessage()
            ->getHeaders()
            ->get('X-Resend-Email-ID');

        if ($header === null) {
            return null;
        }

        $messageId = $header->getBodyAsString();

        return $messageId !== '' ? $messageId : null;
    }
}
