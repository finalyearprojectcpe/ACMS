<x-mail::message>
# Faculty head invitation

@if (is_string($recipientName) && $recipientName !== '')
Hello {{ $recipientName }},
@endif

You have been invited to take over a faculty head account for {{ $schoolName }} on GradeManager.

Use this one-time access code when accepting the invitation:

<x-mail::panel>
{{ $accessCode }}
</x-mail::panel>

<x-mail::button :url="$inviteUrl">
Review invitation
</x-mail::button>

The current faculty head will lose faculty head access after you accept the transfer.

GradeManager
</x-mail::message>
