<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class StudentJoinMail extends GradeManagerMail
{
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.student-join',
            with: [
                'kind' => data_get($this->delivery->metadata, 'kind'),
                'studentName' => data_get($this->delivery->metadata, 'student_name'),
                'setName' => data_get($this->delivery->metadata, 'set_name'),
                'url' => data_get($this->delivery->metadata, 'url'),
                'reason' => data_get($this->delivery->metadata, 'reason'),
            ],
        );
    }
}
