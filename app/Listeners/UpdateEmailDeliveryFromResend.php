<?php

namespace App\Listeners;

use App\EmailDeliveryStatus;
use App\Models\EmailDelivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Resend\Laravel\Events\EmailBounced;
use Resend\Laravel\Events\EmailComplained;
use Resend\Laravel\Events\EmailDelivered;
use Resend\Laravel\Events\EmailDeliveryDelayed;
use Resend\Laravel\Events\EmailFailed;
use Resend\Laravel\Events\EmailSent;
use Resend\Laravel\Events\EmailSuppressed;

class UpdateEmailDeliveryFromResend
{
    public function handle(object $event): void
    {
        if (! property_exists($event, 'payload') || ! is_array($event->payload)) {
            return;
        }

        $providerMessageId = data_get($event->payload, 'data.email_id');
        $deliveryId = $this->deliveryIdFromTags(data_get($event->payload, 'data.tags'));

        if ((! is_string($providerMessageId) || $providerMessageId === '') && $deliveryId === null) {
            return;
        }

        $delivery = is_string($providerMessageId) && $providerMessageId !== ''
            ? EmailDelivery::query()->where('provider_message_id', $providerMessageId)->first()
            : null;
        $delivery ??= $deliveryId !== null
            ? EmailDelivery::query()->find($deliveryId)
            : null;

        if (! $delivery instanceof EmailDelivery) {
            return;
        }

        $eventAt = Carbon::parse((string) data_get($event->payload, 'created_at', now()->toIso8601String()));

        if ($delivery->provider_event_at?->isAfter($eventAt)) {
            return;
        }

        $status = $this->statusFor($event);
        $error = $this->errorFor($event->payload, $status);

        $delivery->forceFill([
            'mailer' => 'resend',
            'status' => $status->value,
            'provider_message_id' => is_string($providerMessageId) && $providerMessageId !== ''
                ? $providerMessageId
                : $delivery->provider_message_id,
            'provider_event_at' => $eventAt,
            'sent_at' => $delivery->sent_at ?? (in_array($status, [
                EmailDeliveryStatus::Sent,
                EmailDeliveryStatus::Delivered,
            ], true) ? $eventAt : null),
            'delivered_at' => $status === EmailDeliveryStatus::Delivered
                ? $eventAt
                : $delivery->delivered_at,
            'failed_at' => in_array($status, [
                EmailDeliveryStatus::Failed,
                EmailDeliveryStatus::Bounced,
                EmailDeliveryStatus::Suppressed,
            ], true) ? $eventAt : null,
            'error' => $error,
        ])->save();
    }

    private function statusFor(object $event): EmailDeliveryStatus
    {
        return match (true) {
            $event instanceof EmailDelivered => EmailDeliveryStatus::Delivered,
            $event instanceof EmailDeliveryDelayed => EmailDeliveryStatus::Delayed,
            $event instanceof EmailBounced => EmailDeliveryStatus::Bounced,
            $event instanceof EmailSuppressed => EmailDeliveryStatus::Suppressed,
            $event instanceof EmailComplained => EmailDeliveryStatus::Complained,
            $event instanceof EmailFailed => EmailDeliveryStatus::Failed,
            $event instanceof EmailSent => EmailDeliveryStatus::Sent,
            default => EmailDeliveryStatus::Sent,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function errorFor(array $payload, EmailDeliveryStatus $status): ?string
    {
        if (! in_array($status, [
            EmailDeliveryStatus::Failed,
            EmailDeliveryStatus::Bounced,
            EmailDeliveryStatus::Suppressed,
            EmailDeliveryStatus::Complained,
        ], true)) {
            return null;
        }

        $message = data_get($payload, 'data.bounce.message')
            ?? data_get($payload, 'data.failed.reason')
            ?? data_get($payload, 'data.reason')
            ?? data_get($payload, 'data.message')
            ?? 'Resend reported that the email could not be delivered.';

        return Str::limit((string) $message, 2000);
    }

    private function deliveryIdFromTags(mixed $tags): ?int
    {
        if (is_array($tags) && isset($tags['delivery_id'])) {
            return filter_var($tags['delivery_id'], FILTER_VALIDATE_INT) ?: null;
        }

        if (! is_array($tags)) {
            return null;
        }

        foreach ($tags as $tag) {
            if (is_array($tag) && ($tag['name'] ?? null) === 'delivery_id') {
                return filter_var($tag['value'] ?? null, FILTER_VALIDATE_INT) ?: null;
            }
        }

        return null;
    }
}
