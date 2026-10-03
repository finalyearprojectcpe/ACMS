<x-mail::message>
@if ($kind === 'verification')
# Verify your email

Confirm this address to send your request to join {{ $setName }} to the course adviser.

<x-mail::button :url="$url">Verify email</x-mail::button>

This link expires in 24 hours. If you did not create this account, ignore this email.
@elseif ($kind === 'adviser_review')
# Student request to review

{{ $studentName }} has verified their email and requested to join {{ $setName }}.

<x-mail::button :url="$url">Review request</x-mail::button>
@elseif ($kind === 'approved')
# Your request was accepted

You can now access your student account for {{ $setName }}.

<x-mail::button :url="$url">Open dashboard</x-mail::button>
@elseif ($kind === 'email_change')
# Confirm your email address

Your course adviser updated the email for your ACMS student account. Confirm this address to make it your sign-in email.

<x-mail::button :url="$url">Confirm email</x-mail::button>

This link expires in 24 hours. If you did not expect this change, contact your course adviser.
@elseif ($kind === 'rejected')
# Your request needs correction

Your request to join {{ $setName }} was not accepted.

Reason: {{ $reason }}

<x-mail::button :url="$url">Review your request</x-mail::button>
@endif

ACMS
</x-mail::message>
