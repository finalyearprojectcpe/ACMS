<?php

namespace App\Models;

use Database\Factories\StudentDueAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'due_id',
    'student_profile_id',
    'amount_kobo',
    'status',
    'paid_at',
    'waived_at',
    'waived_by_user_id',
    'waiver_reason',
    'latest_due_payment_id',
])]
class StudentDueAssignment extends Model
{
    /** @use HasFactory<StudentDueAssignmentFactory> */
    use HasFactory;

    public const string StatusPending = 'pending';

    public const string StatusPaid = 'paid';

    public const string StatusWaived = 'waived';

    /**
     * @return BelongsTo<Due, $this>
     */
    public function due(): BelongsTo
    {
        return $this->belongsTo(Due::class);
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * @return BelongsTo<DuePayment, $this>
     */
    public function latestPayment(): BelongsTo
    {
        return $this->belongsTo(DuePayment::class, 'latest_due_payment_id');
    }

    /**
     * @return HasMany<DuePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DuePayment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'waived_at' => 'datetime',
        ];
    }
}
