<?php

namespace App\Services;

use App\Models\DuePayment;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice;

class ReceiptPdfService
{
    public function __construct(
        private readonly ReceiptUrlService $receiptUrls,
        private readonly CurrencyFormatter $currency,
    ) {}

    public function generate(DuePayment $payment): string
    {
        $payment->loadMissing([
            'assignment.due.studentSet',
            'advisorProfile.user',
            'studentProfile.user',
        ]);

        $due = $payment->assignment->due;
        $student = $payment->studentProfile;
        $verificationUrl = $this->receiptUrls->verificationUrl($payment);
        $logo = $this->logoData($payment);
        $qrCode = (new Builder(
            writer: new PngWriter,
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 8,
        ))->build()->getDataUri();
        $seller = new Party([
            'name' => $payment->advisorProfile->user->displayName(),
            'custom_fields' => [
                'Role' => 'Course Adviser',
            ],
        ]);
        $buyer = new Party([
            'name' => $student->user->name,
        ]);
        $item = InvoiceItem::make($due->title)
            ->pricePerUnit($payment->amount_kobo / 100)
            ->quantity(1);
        $invoice = Invoice::make('Payment Receipt')
            ->template('due-payment')
            ->status($payment->status === DuePayment::StatusReversed ? 'REVERSED' : 'PAID')
            ->seller($seller)
            ->buyer($buyer)
            ->addItem($item)
            ->date($payment->paid_at ?? now())
            ->dateFormat('F j, Y')
            ->currencyCode($payment->currency)
            ->currencySymbol('₦')
            ->currencyFormat('{SYMBOL} {VALUE}')
            ->currencyThousandsSeparator(',')
            ->filename('receipt-'.$payment->receipt_number)
            ->setCustomData([
                'payment' => $payment,
                'amount' => $this->currency->nairaFromKobo((int) $payment->amount_kobo),
                'logo' => $logo,
                'qrCode' => $qrCode,
                'verificationUrl' => $verificationUrl,
            ]);

        $invoice->render();

        return $invoice->output;
    }

    private function logoData(DuePayment $payment): ?string
    {
        $path = $payment->receipt_logo_path;
        if (! $path) {
            return null;
        }

        $disks = array_unique([(string) config('filesystems.due_receipt_disk', 'local'), 'local']);
        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if (! $disk->exists($path)) {
                    continue;
                }

                $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';

                return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
            } catch (\Throwable $exception) {
                Log::warning('Receipt logo storage could not be read; trying the next disk.', [
                    'payment_id' => $payment->id,
                    'student_set_id' => $payment->assignment->due->student_set_id,
                    'disk' => $diskName,
                    'path' => $path,
                    'exception' => $exception,
                ]);
            }
        }

        Log::warning('Receipt logo is missing; generating without it.', [
            'payment_id' => $payment->id,
            'student_set_id' => $payment->assignment->due->student_set_id,
            'disks' => $disks,
            'path' => $path,
        ]);

        return null;
    }
}
