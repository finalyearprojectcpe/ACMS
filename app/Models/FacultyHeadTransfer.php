<?php

namespace App\Models;

use Database\Factories\FacultyHeadTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'faculty_id', 'initiated_by_user_id', 'recipient_user_id', 'recipient_staff_profile_id', 'accepted_by_user_id', 'recipient_email', 'recipient_name', 'access_code', 'token_hash', 'status', 'expires_at', 'accepted_at', 'cancelled_at'])]
#[Hidden(['token_hash'])]
class FacultyHeadTransfer extends Model
{
    /** @use HasFactory<FacultyHeadTransferFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Faculty, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function recipientStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'recipient_staff_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->accepted_at === null && $this->cancelled_at === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
