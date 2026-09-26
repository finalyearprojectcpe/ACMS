<x-mail::message>
# Reset your GradeManager password

You requested a password reset for your GradeManager account.

<x-mail::button :url="$resetUrl">
Reset password
</x-mail::button>

This link expires in {{ $expiresAfterMinutes }} minutes and can only be used once. If you did not request a password reset, you can ignore this email.

GradeManager
</x-mail::message>
