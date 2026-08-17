<?php

namespace App\Jobs;

use App\EmailDeliveryStatus;
use App\Models\EmailDelivery;
use App\Services\EmailDeliveryService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Str;
use Throwable;

class SendEmailDelivery implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(public int $emailDeliveryId) {}

    public function handle(EmailDeliveryService $emailDeliveryService): void
    {
        $emailDeliveryService->deliver(
            EmailDelivery::query()->findOrFail($this->emailDeliveryId),
        );
    }

    /**
     * @return array<int, RateLimited>
     */
    public function middleware(): array
    {
        return [(new RateLimited('resend-email'))->releaseAfter(1)];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function uniqueId(): string
    {
        return (string) $this->emailDeliveryId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function failed(?Throwable $exception): void
    {
        EmailDelivery::query()
            ->whereKey($this->emailDeliveryId)
            ->whereNull('provider_event_at')
            ->whereNotIn('status', [
                EmailDeliveryStatus::Sent->value,
                EmailDeliveryStatus::Delivered->value,
            ])
            ->update([
                'status' => EmailDeliveryStatus::Failed->value,
                'error' => Str::limit(
                    $exception?->getMessage() ?? 'Email delivery failed after several attempts.',
                    2000,
                ),
                'failed_at' => now(),
            ]);
    }
}
