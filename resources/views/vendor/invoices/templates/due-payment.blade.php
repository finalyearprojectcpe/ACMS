@php
    $data = $invoice->getCustomData();
    $payment = $data['payment'];
    $due = $payment->assignment->due;
    $student = $payment->studentProfile;
    $isReversed = $payment->status === \App\Models\DuePayment::StatusReversed;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #18181b; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.45; }
        .receipt { border: 1px solid #d4d4d8; }
        .header { padding: 24px; border-bottom: 3px solid {{ $isReversed ? '#b91c1c' : '#166534' }}; }
        .header-table, .summary-table, .details-table, .footer-table { width: 100%; border-collapse: collapse; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        .school { padding-left: 14px; vertical-align: middle; }
        .school-name { margin: 0; font-size: 17px; font-weight: 700; }
        .department { margin: 3px 0 0; color: #71717a; }
        .document { text-align: right; vertical-align: middle; }
        .document-title { margin: 0; font-size: 18px; font-weight: 700; }
        .status { display: inline-block; margin-top: 7px; color: {{ $isReversed ? '#b91c1c' : '#166534' }}; font-size: 11px; font-weight: 700; letter-spacing: .04em; }
        .summary { padding: 22px 24px; background: #fafafa; border-bottom: 1px solid #e4e4e7; }
        .amount-label, .meta-label { color: #71717a; font-size: 10px; text-transform: uppercase; }
        .amount { margin-top: 4px; font-size: 25px; font-weight: 700; }
        .receipt-number { text-align: right; vertical-align: bottom; }
        .receipt-number strong { display: block; margin-top: 4px; font-size: 13px; }
        .details { padding: 24px; }
        .details-table td { width: 50%; padding: 0 18px 18px 0; vertical-align: top; }
        .details-table td:nth-child(2n) { padding-right: 0; }
        .meta-value { margin-top: 4px; font-size: 12px; font-weight: 600; overflow-wrap: anywhere; }
        .verification { margin: 2px 24px 24px; padding: 18px; border: 1px solid #d4d4d8; background: #fafafa; }
        .qr { width: 104px; height: 104px; }
        .verification-copy { padding-left: 18px; vertical-align: middle; }
        .verification-title { margin: 0 0 5px; font-size: 13px; font-weight: 700; }
        .verification-text { margin: 0; color: #52525b; font-size: 10px; }
        .transaction { margin-top: 9px; font-family: DejaVu Sans Mono, monospace; font-size: 10px; font-weight: 700; }
        .footer { padding: 14px 24px; border-top: 1px solid #e4e4e7; color: #71717a; font-size: 9px; }
        .footer-right { text-align: right; }
        .watermark { color: #b91c1c; font-size: 28px; font-weight: 700; text-align: center; padding: 0 24px 18px; }
    </style>
</head>
<body>
<main class="receipt">
    <header class="header">
        <table class="header-table">
            <tr>
                <td style="width: 48px"><img class="logo" src="{{ $data['logo'] }}" alt=""></td>
                <td class="school">
                    <p class="school-name">{{ $payment->advisorProfile->user->displayName() }}</p>
                    <p class="department">Course Adviser</p>
                </td>
                <td class="document">
                    <p class="document-title">Payment Receipt</p>
                    <span class="status">{{ $isReversed ? 'REVERSED' : 'PAYMENT CONFIRMED' }}</span>
                </td>
            </tr>
        </table>
    </header>

    <section class="summary">
        <table class="summary-table">
            <tr>
                <td>
                    <div class="amount-label">Amount Paid</div>
                    <div class="amount">{{ $data['amount'] }}</div>
                </td>
                <td class="receipt-number">
                    <div class="meta-label">Receipt Number</div>
                    <strong>{{ $payment->receipt_number }}</strong>
                </td>
            </tr>
        </table>
    </section>

    <section class="details">
        <table class="details-table">
            <tr>
                <td><div class="meta-label">Student</div><div class="meta-value">{{ $student->user->name }}</div></td>
                <td><div class="meta-label">Registration Number</div><div class="meta-value">{{ $student->registration_number }}</div></td>
            </tr>
            <tr>
                <td><div class="meta-label">Payment For</div><div class="meta-value">{{ $due->title }}</div></td>
                <td><div class="meta-label">Paid On</div><div class="meta-value">{{ $payment->paid_at?->format('F j, Y \a\t g:i A') }}</div></td>
            </tr>
            <tr>
                <td><div class="meta-label">Payment Channel</div><div class="meta-value">{{ $payment->channel ? ucfirst($payment->channel) : 'Paystack' }}</div></td>
                <td><div class="meta-label">Payment Reference</div><div class="meta-value">{{ $payment->reference }}</div></td>
            </tr>
        </table>
    </section>

    @if ($isReversed)
        <div class="watermark">PAYMENT REVERSED</div>
    @endif

    <section class="verification">
        <table class="footer-table">
            <tr>
                <td style="width: 104px"><img class="qr" src="{{ $data['qrCode'] }}" alt="Receipt verification QR code"></td>
                <td class="verification-copy">
                    <p class="verification-title">Scan to verify this receipt</p>
                    <p class="verification-text">The verification page checks this document against GradeManager's Paystack payment record.</p>
                    <div class="transaction">Paystack Transaction ID: {{ $payment->paystack_transaction_id }}</div>
                </td>
            </tr>
        </table>
    </section>

    <footer class="footer">
        <table class="footer-table">
            <tr>
                <td>Generated by GradeManager from a verified Paystack transaction.</td>
                <td class="footer-right">{{ $payment->receipt_generated_at?->format('F j, Y') ?? now()->format('F j, Y') }}</td>
            </tr>
        </table>
    </footer>
</main>
</body>
</html>
