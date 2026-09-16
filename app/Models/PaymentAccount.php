<?php

namespace App\Models;

use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'advisor_profile_id',
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
    'active_marker',
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
    protected $hidden = ['account_number', 'provider_payload'];

    public const string ProviderPaystack = 'paystack';

    public const string ProviderDemo = 'demo';

    public const string StatusDraft = 'draft';

    public const string StatusActive = 'active';

    public const string StatusDisabled = 'disabled';

    protected static function booted(): void
    {
        static::saving(function (PaymentAccount $paymentAccount): void {
            $paymentAccount->active_marker = $paymentAccount->status === self::StatusActive ? 1 : null;
        });
    }

    /** @return BelongsTo<AdvisorProfile, $this> */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
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
        return $this->provider === self::ProviderPaystack
            && $this->status === self::StatusActive
            && is_string($this->paystack_subaccount_code)
            && $this->paystack_subaccount_code !== ''
            && data_get($this->provider_payload, 'demo') !== true;
    }

    public static function currentFor(AdvisorProfile $advisorProfile): ?self
    {
        return self::query()
            ->where('advisor_profile_id', $advisorProfile->id)
            ->latest('updated_at')
            ->latest('id')
            ->get()
            ->first(fn (self $paymentAccount): bool => $paymentAccount->isActive());
    }

    public static function latestFor(AdvisorProfile $advisorProfile): ?self
    {
        return self::query()
            ->where('advisor_profile_id', $advisorProfile->id)
            ->latest('updated_at')
            ->latest('id')
            ->first();
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
            'active_marker' => 'integer',
        ];
    }
}
