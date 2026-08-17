<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class FacultyHeadTransferInvitationMail extends GradeManagerMail
{
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.faculty-head-transfer-invitation',
            with: [
                'accessCode' => (string) data_get($this->delivery->metadata, 'access_code'),
                'inviteUrl' => (string) data_get($this->delivery->metadata, 'invite_url'),
                'recipientName' => data_get($this->delivery->metadata, 'recipient_name'),
                'schoolName' => $this->delivery->school?->name ?? 'the school',
            ],
        );
    }
}
