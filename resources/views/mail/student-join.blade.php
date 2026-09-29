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
@elseif ($kind === 'rejected')
# Your request needs correction

Your request to join {{ $setName }} was not accepted.

Reason: {{ $reason }}

<x-mail::button :url="$url">Review your request</x-mail::button>
@endif

GradeManager
</x-mail::message>
