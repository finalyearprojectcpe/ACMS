<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class PasswordResetMail extends GradeManagerMail
{
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.password-reset',
            with: [
                'resetUrl' => (string) data_get($this->delivery->metadata, 'reset_url'),
                'expiresAfterMinutes' => (int) config('auth.passwords.users.expire', 60),
                'institutionName' => $this->delivery->advisorProfile?->institution_name ?? 'your institution',
            ],
        );
    }
}
