<x-mail::message>
# School admin ownership invitation

@if (is_string($recipientName) && $recipientName !== '')
Hello {{ $recipientName }},
@endif

You have been invited to become the school admin for {{ $schoolName }} on GradeManager.

Use this one-time access code when accepting the invitation:

<x-mail::panel>
{{ $accessCode }}
</x-mail::panel>

<x-mail::button :url="$inviteUrl">
Review invitation
</x-mail::button>

Only one account can be the school admin. The current administrator will lose school admin access after you accept the transfer.

GradeManager
</x-mail::message>
