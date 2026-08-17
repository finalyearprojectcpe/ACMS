<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

class DuePaymentReceiptMail extends GradeManagerMail
{
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.due-payment-receipt',
            with: [
                'dueName' => (string) data_get($this->delivery->metadata, 'due_name'),
                'amount' => (string) data_get($this->delivery->metadata, 'amount'),
                'receiptNumber' => (string) data_get($this->delivery->metadata, 'receipt_number'),
                'downloadUrl' => (string) data_get($this->delivery->metadata, 'download_url'),
                'schoolName' => $this->delivery->school?->name ?? 'your school',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $disk = (string) data_get($this->delivery->metadata, 'attachment.disk');
        $path = (string) data_get($this->delivery->metadata, 'attachment.path');
        $filename = (string) data_get($this->delivery->metadata, 'attachment.filename', basename($path));

        if ($disk === '' || $path === '') {
            return [];
        }

        return [
            Attachment::fromStorageDisk($disk, $path)
                ->as($filename)
                ->withMime('application/pdf'),
        ];
    }
}
