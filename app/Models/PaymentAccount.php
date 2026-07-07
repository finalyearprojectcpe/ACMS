<?php

namespace App\Models;

use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'school_id',
    'department_id',
    'staff_profile_id',
    'entry_academic_session_id',
    'provider',
    'label',
    'business_name',
    'bank_code',
    'bank_name',
    'account_number',
    'account_number_last4',
    'account_name',
    'paystack_subaccount_code',
    'status',
    'verified_at',
    'activated_at',
    'disabled_at',
    'provider_payload',
])]
class PaymentAccount extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $hidden = ['account_number'];

    public const string ProviderPaystack = 'paystack';

    public const string StatusDraft = 'draft';

    public const string StatusActive = 'active';

    public const string StatusDisabled = 'disabled';

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
     * @return BelongsTo<StaffProfile, $this>
     */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function entryAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'entry_academic_session_id');
    }

    /**
     * @return HasMany<Due, $this>
     */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    /**
     * @return HasMany<DuePayment, $this>
     */
    public function duePayments(): HasMany
    {
        return $this->hasMany(DuePayment::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::StatusActive
            && is_string($this->paystack_subaccount_code)
            && $this->paystack_subaccount_code !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }
}
