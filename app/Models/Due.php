<?php

namespace App\Models;

use Database\Factories\DueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'school_id',
    'department_id',
    'entry_academic_session_id',
    'academic_session_id',
    'created_by_staff_profile_id',
    'payment_account_id',
    'title',
    'description',
    'amount_kobo',
    'currency',
    'due_on',
    'status',
    'published_at',
])]
class Due extends Model
{
    /** @use HasFactory<DueFactory> */
    use HasFactory;

    public const string StatusDraft = 'draft';

    public const string StatusPublished = 'published';

    public const string StatusArchived = 'archived';

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function entryAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'entry_academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function createdByStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'created_by_staff_profile_id');
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
        ];
    }
}
