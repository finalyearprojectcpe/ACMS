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
        @page { margin: 27px; }
        * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #171717; font-size: 10px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .masthead { padding-bottom: 15px; border-bottom: 2px solid #171717; }
        .logo-cell { width: 55px; }
        .logo { width: 46px; height: 46px; object-fit: contain; }
        .adviser { margin: 2px 0 0; font-size: 13px; font-weight: 700; }
        .department { margin: 3px 0 0; color: #5b5b5b; font-size: 9px; }
        .title-row { padding-top: 17px; }
        .title { margin: 0; font-size: 18px; font-weight: 700; }
        .status { text-align: right; font-size: 10px; font-weight: 700; vertical-align: middle; }
        .reversed { color: #a11c1c; }
        .amount-block { padding: 18px 0 15px; border-bottom: 1px solid #171717; }
        .label { color: #666; font-size: 9px; }
        .amount { margin-top: 2px; font-size: 27px; font-weight: 700; }
        .purpose { margin-top: 3px; font-size: 11px; }
        .details { padding: 14px 0 17px; }
        .details td { padding: 4px 0; overflow-wrap: anywhere; }
        .details .label-cell { width: 38%; color: #666; font-size: 9px; }
        .details .value-cell { font-size: 10px; font-weight: 700; }
        .verification { padding-top: 14px; border-top: 1px solid #171717; }
        .qr-cell { width: 82px; }
        .qr { width: 72px; height: 72px; }
        .verification-copy { vertical-align: middle; }
        .verification-title { margin: 0 0 3px; font-size: 11px; font-weight: 700; }
        .verification-text { margin: 0; color: #666; font-size: 9px; }
    </style>
</head>
<body>
<header class="masthead">
    <table>
        <tr>
            @if ($data['logo'])
                <td class="logo-cell"><img class="logo" src="{{ $data['logo'] }}" alt=""></td>
            @endif
            <td>
                <p class="adviser">{{ $payment->advisorProfile->user->displayName() }}</p>
                <p class="department">{{ $due->studentSet?->department_name }} · Course Adviser</p>
            </td>
        </tr>
    </table>
</header>

<table class="title-row">
    <tr>
        <td><h1 class="title">Payment Receipt</h1></td>
        <td class="status {{ $isReversed ? 'reversed' : '' }}">{{ $isReversed ? 'REVERSED' : 'PAID' }}</td>
    </tr>
</table>

<section class="amount-block">
    <div class="label">Amount paid</div>
    <div class="amount">{{ $data['amount'] }}</div>
    <div class="purpose">{{ $due->title }}</div>
</section>

<section class="details">
    <table>
        <tr><td class="label-cell">Student</td><td class="value-cell">{{ $student->user->name }}</td></tr>
        <tr><td class="label-cell">Registration number</td><td class="value-cell">{{ $student->registration_number }}</td></tr>
        <tr><td class="label-cell">Paid on</td><td class="value-cell">{{ $payment->paid_at?->format('j F Y, g:i A') }}</td></tr>
        <tr><td class="label-cell">Payment method</td><td class="value-cell">{{ $payment->channel ? ucfirst($payment->channel) : 'Paystack' }}</td></tr>
        <tr><td class="label-cell">Paystack transaction ID</td><td class="value-cell">{{ $payment->paystack_transaction_id ?? $payment->receipt_number }}</td></tr>
        <tr><td class="label-cell">Payment reference</td><td class="value-cell">{{ $payment->reference }}</td></tr>
    </table>
</section>

<section class="verification">
    <table>
        <tr>
            <td class="qr-cell"><img class="qr" src="{{ $data['qrCode'] }}" alt="Receipt verification QR code"></td>
            <td class="verification-copy">
                <p class="verification-title">Verify this payment</p>
                <p class="verification-text">Scan the QR code to check its current status.</p>
            </td>
        </tr>
    </table>
</section>
</body>
</html>
