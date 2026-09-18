<?php

namespace App\Http\Controllers;

use App\Models\DuePayment;
use App\Services\CurrencyFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptVerificationController extends Controller
{
    public function __construct(private readonly CurrencyFormatter $currency) {}

    public function show(Request $request, string $paystackTransactionId): InertiaResponse
    {
        if (! $request->hasValidSignature(absolute: false)) {
            return Inertia::render('receipt-verification', [
                'receipt' => ['status' => 'invalid'],
            ]);
        }

        $payment = $this->payment($paystackTransactionId);
        $registrationNumber = $payment->studentProfile->registration_number;

        return Inertia::render('receipt-verification', [
            'receipt' => [
                'status' => $payment->status === DuePayment::StatusReversed ? 'reversed' : 'valid',
                'receiptNumber' => $payment->receipt_number,
                'transactionId' => $payment->paystack_transaction_id,
                'institution' => $payment->advisorProfile->institution_name ?: config('app.name'),
                'due' => $payment->assignment->due->title,
                'amount' => $this->currency->nairaFromKobo((int) $payment->amount_kobo),
                'paidAt' => $payment->paid_at?->format('F j, Y \a\t g:i A'),
                'registrationNumber' => $this->maskRegistrationNumber($registrationNumber),
            ],
        ]);
    }

    public function download(string $paystackTransactionId): StreamedResponse
    {
        $payment = $this->payment($paystackTransactionId);

        abort_unless(
            $payment->receipt_status === DuePayment::ReceiptStatusReady
            && is_string($payment->receipt_disk)
            && is_string($payment->receipt_path)
            && Storage::disk($payment->receipt_disk)->exists($payment->receipt_path),
            404,
        );

        return Storage::disk($payment->receipt_disk)->download(
            $payment->receipt_path,
            'receipt-'.$payment->receipt_number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function payment(string $paystackTransactionId): DuePayment
    {
        return DuePayment::query()
            ->with(['assignment.due', 'advisorProfile', 'studentProfile.user'])
            ->where('paystack_transaction_id', $paystackTransactionId)
            ->whereIn('status', [DuePayment::StatusSuccess, DuePayment::StatusReversed])
            ->firstOrFail();
    }

    private function maskRegistrationNumber(string $registrationNumber): string
    {
        if (mb_strlen($registrationNumber) <= 6) {
            return $registrationNumber;
        }

        return mb_substr($registrationNumber, 0, 4)
            .str_repeat('*', max(2, mb_strlen($registrationNumber) - 7))
            .mb_substr($registrationNumber, -3);
    }
}
