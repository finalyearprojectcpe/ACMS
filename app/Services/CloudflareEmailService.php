<?php

namespace App\Services;

use App\Models\EmailDelivery;
use Illuminate\Support\Facades\Http;
use Throwable;

class CloudflareEmailService
{
    public function sendAccessCode(EmailDelivery $delivery, string $accessCode): EmailDelivery
    {
        return $this->send($delivery, $this->accessCodeText($accessCode));
    }

    public function sendOwnershipTransferInvitation(EmailDelivery $delivery, string $accessCode, string $inviteUrl): EmailDelivery
    {
        return $this->send($delivery, $this->ownershipTransferText($accessCode, $inviteUrl));
    }

    public function sendFacultyHeadTransferInvitation(EmailDelivery $delivery, string $accessCode, string $inviteUrl): EmailDelivery
    {
        return $this->send($delivery, $this->facultyHeadTransferText($accessCode, $inviteUrl));
    }

    private function send(EmailDelivery $delivery, string $text): EmailDelivery
    {
        $endpoint = config('services.cloudflare_email.endpoint');
        $token = config('services.cloudflare_email.token');

        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            $delivery->forceFill([
                'status' => 'skipped',
                'error' => 'Cloudflare email endpoint is not configured.',
            ])->save();

            return $delivery;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->post($endpoint, [
                    'to' => $delivery->recipient_email,
                    'subject' => $delivery->subject,
                    'text' => $text,
                    'metadata' => $delivery->metadata ?? [],
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
                'error' => $response->body(),
                'failed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'failed_at' => now(),
            ])->save();
        }

        return $delivery;
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
}
