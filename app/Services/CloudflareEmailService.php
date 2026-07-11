<?php

namespace App\Services;

use App\Jobs\SendCloudflareEmail;
use App\Models\EmailDelivery;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CloudflareEmailService
{
    public function sendAccessCode(EmailDelivery $delivery, string $accessCode): EmailDelivery
    {
        return $this->queue($delivery, [
            'template' => 'access_code',
            'access_code' => $accessCode,
        ]);
    }

    public function sendOwnershipTransferInvitation(EmailDelivery $delivery, string $accessCode, string $inviteUrl): EmailDelivery
    {
        return $this->queue($delivery, [
            'template' => 'ownership_transfer',
            'access_code' => $accessCode,
            'invite_url' => $inviteUrl,
        ]);
    }

    public function sendFacultyHeadTransferInvitation(EmailDelivery $delivery, string $accessCode, string $inviteUrl): EmailDelivery
    {
        return $this->queue($delivery, [
            'template' => 'faculty_head_transfer',
            'access_code' => $accessCode,
            'invite_url' => $inviteUrl,
        ]);
    }

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
        ]);
    }

    /**
     * Called by the queued delivery job.
     */
    public function deliver(EmailDelivery $delivery): EmailDelivery
    {
        if ($delivery->status === 'sent') {
            return $delivery;
        }

        $endpoint = config('services.cloudflare_email.endpoint');
        $token = config('services.cloudflare_email.token');

        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            $delivery->forceFill([
                'status' => 'skipped',
                'error' => 'Cloudflare email endpoint is not configured.',
            ])->save();

            return $delivery;
        }

        $text = $this->deliveryText($delivery);

        $delivery->forceFill([
            'status' => 'processing',
            'attempts' => $delivery->attempts + 1,
        ])->save();

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Idempotency-Key' => 'email-delivery-'.$delivery->id,
                ])
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->post($endpoint, [
                    'to' => $delivery->recipient_email,
                    'subject' => $delivery->subject,
                    'text' => $text,
                    'metadata' => Arr::except($delivery->metadata ?? [], ['attachment']),
                    'attachments' => $this->deliveryAttachments($delivery),
                ]);

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => 'sent',
                    'provider_message_id' => $response->json('id'),
                    'sent_at' => now(),
                    'error' => null,
                ])->save();

                return $delivery;
            }

            $delivery->forceFill([
                'status' => 'failed',
                'error' => Str::limit($response->body(), 2000),
                'failed_at' => now(),
            ])->save();

            $response->throw();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 2000),
                'failed_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function queue(EmailDelivery $delivery, array $metadata): EmailDelivery
    {
        $delivery->forceFill([
            'status' => 'queued',
            'error' => null,
            'failed_at' => null,
            'metadata' => [
                ...($delivery->metadata ?? []),
                ...$metadata,
            ],
        ])->save();

        SendCloudflareEmail::dispatch($delivery)->afterCommit();

        return $delivery;
    }

    private function deliveryText(EmailDelivery $delivery): string
    {
        $metadata = $delivery->metadata ?? [];
        $accessCode = (string) ($metadata['access_code'] ?? '');
        $inviteUrl = (string) ($metadata['invite_url'] ?? '');
        $resetUrl = (string) ($metadata['reset_url'] ?? '');
        $dueName = (string) ($metadata['due_name'] ?? 'your payment');
        $amount = (string) ($metadata['amount'] ?? '');
        $receiptNumber = (string) ($metadata['receipt_number'] ?? '');
        $downloadUrl = (string) ($metadata['download_url'] ?? '');

        return match ($metadata['template'] ?? 'access_code') {
            'ownership_transfer' => $this->ownershipTransferText($accessCode, $inviteUrl),
            'faculty_head_transfer' => $this->facultyHeadTransferText($accessCode, $inviteUrl),
            'password_reset' => $this->passwordResetText($resetUrl),
            'due_payment_receipt' => $this->duePaymentReceiptText($dueName, $amount, $receiptNumber, $downloadUrl),
            default => $this->accessCodeText($accessCode),
        };
    }

    private function accessCodeText(string $accessCode): string
    {
        return "Your GradeManager access code is {$accessCode}. This code can only be used once.";
    }

    private function ownershipTransferText(string $accessCode, string $inviteUrl): string
    {
        return "You have been invited to take over the GradeManager school admin account. Open {$inviteUrl} and enter access code {$accessCode}. This code can only be used once.";
    }

    private function facultyHeadTransferText(string $accessCode, string $inviteUrl): string
    {
        return "You have been invited to take over a GradeManager faculty head account. Open {$inviteUrl} and enter access code {$accessCode}. This code can only be used once.";
    }

    private function passwordResetText(string $resetUrl): string
    {
        $expiresAfterMinutes = (int) config('auth.passwords.users.expire', 60);

        return "Open {$resetUrl} to reset your GradeManager password. This link expires in {$expiresAfterMinutes} minutes and can only be used once.";
    }

    /**
     * @return array<int, array{filename: string, content_type: string, content_base64: string}>
     */
    private function deliveryAttachments(EmailDelivery $delivery): array
    {
        $attachment = data_get($delivery->metadata, 'attachment');

        if (! is_array($attachment)) {
            return [];
        }

        $disk = (string) ($attachment['disk'] ?? '');
        $path = (string) ($attachment['path'] ?? '');

        if ($disk === '' || $path === '') {
            return [];
        }

        return [[
            'filename' => (string) ($attachment['filename'] ?? basename($path)),
            'content_type' => (string) ($attachment['content_type'] ?? 'application/octet-stream'),
            'content_base64' => base64_encode(Storage::disk($disk)->get($path)),
        ]];
    }

    private function duePaymentReceiptText(
        string $dueName,
        string $amount,
        string $receiptNumber,
        string $downloadUrl,
    ): string {
        return "Your payment of {$amount} for {$dueName} has been confirmed. Your receipt {$receiptNumber} is attached. You can also download it from {$downloadUrl}.";
    }
}
