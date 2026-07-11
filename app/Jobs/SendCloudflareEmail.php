<?php

namespace App\Jobs;

use App\Models\EmailDelivery;
use App\Services\CloudflareEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class SendCloudflareEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public EmailDelivery $delivery) {}

    /**
     * Execute the job.
     */
    public function handle(CloudflareEmailService $cloudflareEmailService): void
    {
        $cloudflareEmailService->deliver($this->delivery);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery->forceFill([
            'status' => 'failed',
            'error' => Str::limit(
                $exception?->getMessage() ?? 'Email delivery failed after several attempts.',
                2000,
            ),
            'failed_at' => now(),
        ])->save();
    }
}
