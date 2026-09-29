<?php

namespace App\Services;

use App\Models\DuePayment;
use App\Models\PaystackWebhookEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PaystackWebhookService
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly DuePaymentService $duePaymentService,
    ) {}

    public function process(string $rawPayload, ?string $signature): int
    {
        if (! $this->paystack->validSignature($rawPayload, $signature)) {
            return 401;
        }

        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return 204;
        }

        $eventName = (string) Arr::get($payload, 'event', '');
        $reference = Arr::get($payload, 'data.reference')
            ?? Arr::get($payload, 'data.transaction.reference');
        $webhookEvent = PaystackWebhookEvent::query()->firstOrCreate(
            ['payload_hash' => hash('sha256', $rawPayload)],
            [
                'event' => $eventName,
                'reference' => is_string($reference) ? $reference : null,
                'signature' => $signature,
                'payload' => $this->safePayload($payload),
            ],
        );

        if ($webhookEvent->processed_at !== null) {
            return 204;
        }

        try {
            $this->processPaymentEvent($eventName, $reference, $payload);
        } catch (\Throwable $throwable) {
            $webhookEvent->forceFill([
                'processing_error' => Str::limit($throwable->getMessage(), 2000),
            ])->save();

            return 500;
        }

        $webhookEvent->forceFill([
            'processed_at' => now(),
            'processing_error' => null,
        ])->save();

        return 204;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processPaymentEvent(string $eventName, mixed $reference, array $payload): void
    {
        if (! is_string($reference) || $reference === '') {
            return;
        }

        $payment = DuePayment::query()->where('reference', $reference)->first();

        if (! ($payment instanceof DuePayment)) {
            return;
        }

        if (in_array($eventName, ['charge.success', 'transaction.successful'], true)) {
            $this->duePaymentService->verifyPayment($payment);

            return;
        }

        if (in_array($eventName, ['refund.processed', 'charge.dispute.create'], true)) {
            $this->duePaymentService->reversePayment(
                $payment,
                (array) Arr::get($payload, 'data', []),
                $eventName === 'refund.processed' ? 'Paystack refund processed' : 'Paystack dispute opened',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        $data = Arr::get($payload, 'data', []);

        return [
            'event' => Arr::get($payload, 'event'),
            'data' => is_array($data)
                ? Arr::only($data, [
                    'id',
                    'status',
                    'reference',
                    'amount',
                    'currency',
                    'paid_at',
                    'channel',
                    'fees',
                    'gateway_response',
                    'metadata',
                    'transaction',
                ])
                : [],
        ];
    }
}
