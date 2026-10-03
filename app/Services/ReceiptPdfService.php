<?php

namespace App\Services;

use App\Models\DuePayment;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
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
        $logo = null;
        if ($payment->receipt_logo_path && Storage::disk('local')->exists($payment->receipt_logo_path)) {
            $mime = str_ends_with(strtolower($payment->receipt_logo_path), '.png') ? 'image/png' : 'image/jpeg';
            $logo = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($payment->receipt_logo_path));
        }
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
}
