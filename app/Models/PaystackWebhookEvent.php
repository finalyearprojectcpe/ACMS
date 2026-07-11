<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event',
    'reference',
    'signature',
    'payload_hash',
    'payload',
    'processed_at',
    'processing_error',
])]
class PaystackWebhookEvent extends Model
{
    /** @var list<string> */
    protected $hidden = ['signature', 'payload'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
