<?php

namespace App\Services;

use App\Models\DuePayment;
use Illuminate\Support\Facades\URL;

class ReceiptUrlService
{
    public function verificationUrl(DuePayment $payment): string
    {
        return URL::signedRoute(
            'receipts.verify',
            ['paystackTransactionId' => $payment->paystack_transaction_id],
            null,
            false,
        );
    }

    public function temporaryDownloadUrl(DuePayment $payment): string
    {
        return URL::temporarySignedRoute(
            'receipts.download',
            now()->addDays(7),
            ['paystackTransactionId' => $payment->paystack_transaction_id],
        );
    }
}
