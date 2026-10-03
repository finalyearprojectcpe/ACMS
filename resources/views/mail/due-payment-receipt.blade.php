<x-mail::message>
# Payment confirmed

Your payment of **{{ $amount }}** for **{{ $dueName }}** has been confirmed.

Receipt number: **{{ $receiptNumber }}**

Your PDF receipt is attached to this email.

<x-mail::button :url="$downloadUrl">
Download receipt
</x-mail::button>

The download link is available for seven days. Your receipt remains available from your ACMS dues page after the link expires.

{{ $courseAdviserName }}<br>
ACMS
</x-mail::message>
