<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class AccessCodeMail extends GradeManagerMail
{
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.access-code',
            with: [
                'accessCode' => (string) data_get($this->delivery->metadata, 'access_code'),
                'loginUrl' => (string) data_get($this->delivery->metadata, 'login_url'),
                'role' => (string) data_get($this->delivery->metadata, 'provisioned_role', 'account'),
                'schoolName' => $this->delivery->school?->name ?? 'your school',
            ],
        );
    }
}
