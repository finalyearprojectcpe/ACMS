<?php

namespace App\Models;

use Database\Factories\DueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'advisor_profile_id',
    'student_set_id',
    'academic_session_id',
    'payment_account_id',
    'title',
    'description',
    'amount_kobo',
    'currency',
    'due_on',
    'status',
    'published_at',
    'archived_at',
])]
class Due extends Model
{
    /** @use HasFactory<DueFactory> */
    use HasFactory;

    public const string StatusDraft = 'draft';

    public const string StatusPublished = 'published';

    public const string StatusArchived = 'archived';

    /**
     * @return BelongsTo<AdvisorProfile, $this>
     */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /**
     * @return BelongsTo<StudentSet, $this>
     */
    public function studentSet(): BelongsTo
    {
        return $this->belongsTo(StudentSet::class);
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<PaymentAccount, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * @return HasMany<StudentDueAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(StudentDueAssignment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
