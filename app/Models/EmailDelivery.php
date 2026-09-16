<?php

namespace App\Models;

use Database\Factories\EmailDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['advisor_profile_id', 'user_id', 'recipient_email', 'subject', 'mailer', 'purpose_key', 'status', 'attempts', 'idempotency_key', 'provider_message_id', 'provider_event_at', 'error', 'metadata', 'sent_at', 'delivered_at', 'failed_at'])]
class EmailDelivery extends Model
{
    /** @use HasFactory<EmailDeliveryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $hidden = ['metadata'];

    /**
     * @return BelongsTo<AdvisorProfile, $this>
     */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'attempts' => 'integer',
            'provider_event_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
