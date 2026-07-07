<?php

namespace App\Models;

use Database\Factories\DuePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_due_assignment_id',
    'student_profile_id',
    'school_id',
    'payment_account_id',
    'provider',
    'reference',
    'status',
    'amount_kobo',
    'currency',
    'authorization_url',
    'access_code',
    'paystack_transaction_id',
    'channel',
    'fees_kobo',
    'gateway_response',
    'paid_at',
    'receipt_number',
    'provider_payload',
])]
class DuePayment extends Model
{
    /** @use HasFactory<DuePaymentFactory> */
    use HasFactory;

    public const string ProviderPaystack = 'paystack';

    public const string StatusInitialized = 'initialized';

    public const string StatusSuccess = 'success';

    public const string StatusFailed = 'failed';

    public const string StatusAbandoned = 'abandoned';

    public const string StatusReversed = 'reversed';

    /**
     * @return BelongsTo<StudentDueAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(StudentDueAssignment::class, 'student_due_assignment_id');
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<PaymentAccount, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }
}
