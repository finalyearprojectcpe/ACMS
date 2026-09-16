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
    'advisor_profile_id',
    'payment_account_id',
    'provider',
    'reference',
    'status',
    'active_marker',
    'amount_kobo',
    'currency',
    'authorization_url',
    'access_code',
    'paystack_transaction_id',
    'channel',
    'fees_kobo',
    'gateway_response',
    'paid_at',
    'reversed_at',
    'reversal_reason',
    'receipt_number',
    'receipt_status',
    'receipt_disk',
    'receipt_path',
    'receipt_generated_at',
    'receipt_generation_error',
    'receipt_generation_attempts',
    'receipt_email_delivery_id',
    'provider_payload',
])]
class DuePayment extends Model
{
    /** @use HasFactory<DuePaymentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $hidden = ['access_code', 'authorization_url', 'provider_payload'];

    public const string ProviderPaystack = 'paystack';

    public const string StatusInitialized = 'initialized';

    public const string StatusSuccess = 'success';

    public const string StatusFailed = 'failed';

    public const string StatusAbandoned = 'abandoned';

    public const string StatusReversed = 'reversed';

    public const string ReceiptStatusPending = 'pending';

    public const string ReceiptStatusProcessing = 'processing';

    public const string ReceiptStatusReady = 'ready';

    public const string ReceiptStatusFailed = 'failed';

    protected static function booted(): void
    {
        static::saving(function (DuePayment $duePayment): void {
            $duePayment->active_marker = $duePayment->status === self::StatusInitialized ? 1 : null;
        });
    }

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
     * @return BelongsTo<AdvisorProfile, $this>
     */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /**
     * @return BelongsTo<PaymentAccount, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * @return BelongsTo<EmailDelivery, $this>
     */
    public function receiptEmailDelivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class, 'receipt_email_delivery_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
            'receipt_generated_at' => 'datetime',
            'provider_payload' => 'array',
            'active_marker' => 'integer',
            'receipt_generation_attempts' => 'integer',
        ];
    }
}
