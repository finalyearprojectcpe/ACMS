<?php

namespace App\Jobs;

use App\Models\DuePayment;
use App\Services\DueReceiptService;
use App\Services\ReceiptPdfService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateDuePaymentReceipt implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public int $duePaymentId) {}

    public function handle(ReceiptPdfService $receiptPdf, DueReceiptService $receipts): void
    {
        $payment = DuePayment::query()
            ->with(['advisorProfile', 'assignment.due', 'studentProfile.user'])
            ->findOrFail($this->duePaymentId);

        if (! in_array($payment->status, [DuePayment::StatusSuccess, DuePayment::StatusReversed], true)) {
            return;
        }

        $payment->forceFill([
            'receipt_status' => DuePayment::ReceiptStatusProcessing,
            'receipt_generation_attempts' => $payment->receipt_generation_attempts + 1,
            'receipt_generation_error' => null,
        ])->save();

        $disk = (string) config('filesystems.due_receipt_disk', 'local');
        $path = sprintf(
            'receipts/%d/%s/%s.pdf',
            $payment->advisor_profile_id,
            ($payment->paid_at ?? now())->format('Y'),
            Str::slug((string) $payment->receipt_number),
        );
        $stored = Storage::disk($disk)->put($path, $receiptPdf->generate($payment));

        if (! $stored) {
            throw new \RuntimeException('The generated receipt could not be stored.');
        }

        $payment->forceFill([
            'receipt_status' => DuePayment::ReceiptStatusReady,
            'receipt_disk' => $disk,
            'receipt_path' => $path,
            'receipt_generated_at' => now(),
            'receipt_generation_error' => null,
        ])->save();

        if ($payment->status === DuePayment::StatusSuccess) {
            try {
                $receipts->queueEmail($payment);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function uniqueId(): string
    {
        return (string) $this->duePaymentId;
    }

    public function failed(?Throwable $exception): void
    {
        DuePayment::query()->whereKey($this->duePaymentId)->update([
            'receipt_status' => DuePayment::ReceiptStatusFailed,
            'receipt_generation_error' => Str::limit(
                $exception?->getMessage() ?? 'Receipt generation failed after several attempts.',
                2000,
            ),
        ]);
    }
}
