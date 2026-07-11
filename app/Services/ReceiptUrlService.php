<?php

namespace App\Services;

use App\Models\DuePayment;
use App\Models\School;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ReceiptUrlService
{
    public function verificationUrl(DuePayment $payment): string
    {
        return $this->absoluteSchoolUrl(
            $payment->school,
            URL::signedRoute(
                'receipts.verify',
                ['paystackTransactionId' => $payment->paystack_transaction_id],
                absolute: false,
            ),
        );
    }

    public function temporaryDownloadUrl(DuePayment $payment): string
    {
        return $this->absoluteSchoolUrl(
            $payment->school,
            URL::temporarySignedRoute(
                'receipts.download',
                now()->addDays(7),
                ['paystackTransactionId' => $payment->paystack_transaction_id],
                absolute: false,
            ),
        );
    }

    private function absoluteSchoolUrl(School $school, string $relativeUrl): string
    {
        $applicationUrl = rtrim((string) config('app.url'), '/');
        $schoolDomain = trim((string) config('app.school_domain'), '.');

        if ($schoolDomain === '') {
            return $applicationUrl.'/'.ltrim($relativeUrl, '/');
        }

        $scheme = parse_url($applicationUrl, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($applicationUrl, PHP_URL_PORT);
        $host = Str::lower($school->abbreviation).'.'.$schoolDomain;

        return sprintf(
            '%s://%s%s/%s',
            $scheme,
            $host,
            is_int($port) ? ':'.$port : '',
            ltrim($relativeUrl, '/'),
        );
    }
}
