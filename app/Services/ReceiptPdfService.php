<?php

namespace App\Services;

use App\Models\DuePayment;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
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
            'assignment.due',
            'advisorProfile',
            'studentProfile.user',
        ]);

        $due = $payment->assignment->due;
        $student = $payment->studentProfile;
        $verificationUrl = $this->receiptUrls->verificationUrl($payment);
        $qrCode = (new Builder(
            writer: new PngWriter,
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 8,
        ))->build()->getDataUri();
        $seller = new Party([
            'name' => $payment->advisorProfile->institution_name
                ?: $payment->advisorProfile->programme_name,
            'custom_fields' => [
                'Programme' => $payment->advisorProfile->programme_name,
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
            ->currencySymbol('NGN')
            ->currencyFormat('{SYMBOL} {VALUE}')
            ->currencyThousandsSeparator(',')
            ->filename('receipt-'.$payment->receipt_number)
            ->setCustomData([
                'payment' => $payment,
                'amount' => $this->currency->nairaFromKobo((int) $payment->amount_kobo),
                'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('logo.png'))),
                'qrCode' => $qrCode,
                'verificationUrl' => $verificationUrl,
            ]);

        $invoice->render();

        return $invoice->output;
    }
}
