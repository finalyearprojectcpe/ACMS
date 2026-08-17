<?php

namespace App\Mail;

use App\Models\EmailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

abstract class GradeManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmailDelivery $delivery) {}

    final public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->delivery->subject,
            metadata: [
                'delivery_id' => $this->delivery->id,
                'school_id' => $this->delivery->school_id,
                'email_type' => (string) data_get($this->delivery->metadata, 'template', 'transactional'),
            ],
        );
    }

    final public function headers(): Headers
    {
        return new Headers(text: [
            'Resend-Idempotency-Key' => (string) $this->delivery->idempotency_key,
            'X-Entity-Ref-ID' => 'email-delivery-'.$this->delivery->id,
        ]);
    }
}
