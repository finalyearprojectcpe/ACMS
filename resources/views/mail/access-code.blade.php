<x-mail::message>
# Your GradeManager access code

An account has been prepared for you on {{ $schoolName }} GradeManager.

Use this one-time access code to complete your account setup:

<x-mail::panel>
{{ $accessCode }}
</x-mail::panel>

<x-mail::button :url="$loginUrl">
Open GradeManager
</x-mail::button>

This code can only be used once. If you were not expecting this account, you can ignore this email.

GradeManager
</x-mail::message>
