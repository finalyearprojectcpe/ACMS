<?php

namespace App\Services;

use App\Models\DuePayment;
use App\Models\PaymentAccount;
use App\Models\StudentDueAssignment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DuePaymentService
{
    public function __construct(
        private readonly PaystackService $paystack,
        private readonly DueReceiptService $receipts,
    ) {}

    /**
     * @param  callable(DuePayment): string  $callbackUrl
     */
    public function startPayment(StudentDueAssignment $assignment, callable $callbackUrl): DuePayment
    {
        [$payment, $needsInitialization] = DB::transaction(function () use ($assignment): array {
            $assignment = StudentDueAssignment::query()
                ->with(['due.advisorProfile', 'studentProfile.user', 'latestPayment'])
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status === StudentDueAssignment::StatusPaid) {
                $successfulPayment = $assignment->latestPayment instanceof DuePayment
                    ? $assignment->latestPayment
                    : $assignment->payments()->where('status', DuePayment::StatusSuccess)->latest()->firstOrFail();

                return [$successfulPayment, false];
            }

            if ($assignment->status === StudentDueAssignment::StatusWaived) {
                throw new RuntimeException('This due has been waived and no payment is required.');
            }

            $advisorProfile = $assignment->due->advisorProfile;
            $paymentAccount = $advisorProfile !== null
                ? PaymentAccount::currentFor($advisorProfile)
                : null;

            if (! ($paymentAccount instanceof PaymentAccount)) {
                throw new PaystackException('This due does not have a usable payout account.');
            }

            $activePayment = $assignment->payments()
                ->where('active_marker', 1)
                ->lockForUpdate()
                ->latest()
                ->first();

            if (
                $activePayment instanceof DuePayment
                && data_get($activePayment->provider_payload, 'payout_account.subaccount_code') === $paymentAccount->paystack_subaccount_code
                && is_string($activePayment->authorization_url)
                && $activePayment->authorization_url !== ''
                && $activePayment->created_at?->isAfter(now()->subMinutes(30))
            ) {
                return [$activePayment, false];
            }

            if ($activePayment instanceof DuePayment) {
                $activePayment->forceFill([
                    'status' => DuePayment::StatusAbandoned,
                    'gateway_response' => 'Checkout was replaced before completion.',
                ])->save();
            }

            $payment = $assignment->payments()->create([
                'student_profile_id' => $assignment->student_profile_id,
                'advisor_profile_id' => $assignment->studentProfile->advisor_profile_id,
                'payment_account_id' => $paymentAccount->id,
                'provider' => DuePayment::ProviderPaystack,
                'reference' => $this->newReference($assignment),
                'status' => DuePayment::StatusInitialized,
                'amount_kobo' => $assignment->amount_kobo,
                'currency' => $assignment->due->currency,
                'provider_payload' => [
                    'payout_account' => $this->payoutAccountSnapshot($paymentAccount),
                ],
            ]);

            return [$payment, true];
        });

        if (! $needsInitialization) {
            return $payment->refresh();
        }

        try {
            $paystackData = $this->paystack->initializeTransaction($payment, $callbackUrl($payment));
            $authorizationUrl = Arr::get($paystackData, 'authorization_url');

            if (! is_string($authorizationUrl) || $authorizationUrl === '') {
                throw new RuntimeException('Paystack did not return a checkout link.');
            }

            $payment->forceFill([
                'authorization_url' => $authorizationUrl,
                'access_code' => Arr::get($paystackData, 'access_code'),
                'provider_payload' => $this->providerPayload($payment, $paystackData),
            ])->save();
        } catch (\Throwable $throwable) {
            if ($throwable instanceof PaystackException && $throwable->isInvalidSubaccount()) {
                $failedPaymentAccount = PaymentAccount::query()->find($payment->payment_account_id);

                if ($failedPaymentAccount instanceof PaymentAccount) {
                    $failedPaymentAccount->forceFill([
                        'status' => PaymentAccount::StatusDisabled,
                        'active_marker' => null,
                        'disabled_at' => now(),
                    ])->save();
                }
            }

            $payment->forceFill([
                'status' => DuePayment::StatusFailed,
                'gateway_response' => 'Paystack could not initialize the checkout.',
            ])->save();

            throw $throwable;
        }

        return $payment->refresh();
    }

    public function verifyPayment(DuePayment $payment): DuePayment
    {
        return $this->recordPaystackData($payment, $this->paystack->verifyTransaction($payment->reference));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordPaystackData(DuePayment $payment, array $data): DuePayment
    {
        $verificationError = null;
        $shouldQueueReceipt = false;
        $verifiedPayment = DB::transaction(function () use ($payment, $data, &$verificationError, &$shouldQueueReceipt): DuePayment {
            $payment = DuePayment::query()
                ->with('assignment')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $status = (string) Arr::get($data, 'status', DuePayment::StatusFailed);
            $amount = (int) Arr::get($data, 'amount', 0);
            $currency = (string) Arr::get($data, 'currency', $payment->currency);
            $reference = (string) Arr::get($data, 'reference', '');
            $metadata = Arr::get($data, 'metadata', []);

            if (in_array($payment->status, [DuePayment::StatusSuccess, DuePayment::StatusReversed], true)) {
                $shouldQueueReceipt = $payment->paystack_transaction_id !== null
                    && ! in_array($payment->receipt_status, [
                        DuePayment::ReceiptStatusPending,
                        DuePayment::ReceiptStatusProcessing,
                        DuePayment::ReceiptStatusReady,
                    ], true);

                return $payment->fresh(['assignment.due', 'studentProfile.user']);
            }

            if (
                $reference !== $payment->reference
                || ! is_array($metadata)
                || (int) Arr::get($metadata, 'advisor_profile_id') !== (int) $payment->advisor_profile_id
                || (int) Arr::get($metadata, 'due_id') !== (int) $payment->assignment->due_id
                || (int) Arr::get($metadata, 'student_due_assignment_id') !== (int) $payment->student_due_assignment_id
                || (int) Arr::get($metadata, 'student_profile_id') !== (int) $payment->student_profile_id
                || (int) Arr::get($metadata, 'payment_account_id') !== (int) $payment->payment_account_id
            ) {
                $payment->forceFill([
                    'status' => DuePayment::StatusFailed,
                    'gateway_response' => 'Reference or payment metadata mismatch',
                    'provider_payload' => $this->providerPayload($payment, $data),
                ])->save();

                $verificationError = 'Paystack returned a transaction that does not belong to this due.';

                return $payment->fresh(['assignment.due', 'studentProfile.user']);
            }

            if ($amount !== (int) $payment->amount_kobo || strtoupper($currency) !== strtoupper($payment->currency)) {
                $payment->forceFill([
                    'status' => DuePayment::StatusFailed,
                    'gateway_response' => 'Amount or currency mismatch',
                    'provider_payload' => $this->providerPayload($payment, $data),
                ])->save();

                $verificationError = 'Paystack returned a payment that does not match this due.';

                return $payment->fresh(['assignment.due', 'studentProfile.user']);
            }

            $paidAt = Arr::get($data, 'paid_at') ?? Arr::get($data, 'paidAt');
            $normalizedStatus = match ($status) {
                DuePayment::StatusSuccess => DuePayment::StatusSuccess,
                DuePayment::StatusAbandoned => DuePayment::StatusAbandoned,
                DuePayment::StatusFailed => DuePayment::StatusFailed,
                default => DuePayment::StatusInitialized,
            };
            $transactionId = Arr::get($data, 'id');
            $payment->forceFill([
                'status' => $normalizedStatus,
                'paystack_transaction_id' => is_scalar($transactionId)
                    ? (string) $transactionId
                    : $payment->paystack_transaction_id,
                'channel' => Arr::get($data, 'channel'),
                'fees_kobo' => Arr::get($data, 'fees'),
                'gateway_response' => Arr::get($data, 'gateway_response'),
                'paid_at' => $normalizedStatus === DuePayment::StatusSuccess && is_string($paidAt) ? $paidAt : $payment->paid_at,
                'provider_payload' => $this->providerPayload($payment, $data),
            ])->save();

            if ($normalizedStatus === DuePayment::StatusSuccess) {
                $receiptNumber = $payment->receipt_number
                    ?? sprintf('GM-%s-%06d', now()->format('Ymd'), $payment->id);

                $payment->forceFill([
                    'receipt_number' => $receiptNumber,
                    'paid_at' => $payment->paid_at ?? now(),
                ])->save();

                $payment->assignment->forceFill([
                    'status' => StudentDueAssignment::StatusPaid,
                    'amount_kobo' => $payment->amount_kobo,
                    'paid_at' => $payment->paid_at,
                    'latest_due_payment_id' => $payment->id,
                ])->save();
                $shouldQueueReceipt = true;
            }

            return $payment->fresh(['assignment.due', 'studentProfile.user']);
        });

        if (is_string($verificationError)) {
            throw new RuntimeException($verificationError);
        }

        if ($shouldQueueReceipt) {
            $verifiedPayment = $this->receipts->queue($verifiedPayment);
        }

        return $verifiedPayment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reversePayment(DuePayment $payment, array $data, string $reason): DuePayment
    {
        $reversedPayment = DB::transaction(function () use ($payment, $data, $reason): DuePayment {
            $payment = DuePayment::query()
                ->with('assignment')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === DuePayment::StatusReversed) {
                return $payment;
            }

            if ($payment->status !== DuePayment::StatusSuccess) {
                return $payment;
            }

            $payment->forceFill([
                'status' => DuePayment::StatusReversed,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'provider_payload' => $this->providerPayload($payment, $data),
            ])->save();

            if ((int) $payment->assignment->latest_due_payment_id === (int) $payment->id) {
                $replacementPayment = $payment->assignment->payments()
                    ->where('id', '!=', $payment->id)
                    ->where('status', DuePayment::StatusSuccess)
                    ->latest('paid_at')
                    ->first();

                $payment->assignment->forceFill([
                    'status' => $replacementPayment instanceof DuePayment
                        ? StudentDueAssignment::StatusPaid
                        : StudentDueAssignment::StatusPending,
                    'paid_at' => $replacementPayment?->paid_at,
                    'latest_due_payment_id' => $replacementPayment?->id,
                ])->save();
            }

            return $payment->fresh(['assignment.due', 'studentProfile.user']);
        });

        if ($reversedPayment->status === DuePayment::StatusReversed) {
            $this->receipts->queue($reversedPayment, force: true);
        }

        return $reversedPayment;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function safeProviderPayload(array $data): array
    {
        return Arr::only($data, [
            'id',
            'status',
            'reference',
            'amount',
            'currency',
            'paid_at',
            'paidAt',
            'channel',
            'fees',
            'gateway_response',
            'metadata',
            'subaccount',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function providerPayload(DuePayment $payment, array $data): array
    {
        $snapshot = data_get($payment->provider_payload, 'payout_account');

        return [
            ...$this->safeProviderPayload($data),
            'payout_account' => is_array($snapshot) ? $snapshot : [],
        ];
    }

    /**
     * @return array{id: int, bank_name: string, account_name: string, account_number_last4: string, subaccount_code: string}
     */
    private function payoutAccountSnapshot(PaymentAccount $paymentAccount): array
    {
        return [
            'id' => (int) $paymentAccount->id,
            'bank_name' => $paymentAccount->bank_name,
            'account_name' => $paymentAccount->account_name,
            'account_number_last4' => $paymentAccount->account_number_last4,
            'subaccount_code' => (string) $paymentAccount->paystack_subaccount_code,
        ];
    }

    private function newReference(StudentDueAssignment $assignment): string
    {
        return sprintf(
            'DUE-%d-%d-%s',
            $assignment->due_id,
            $assignment->student_profile_id,
            Str::upper(Str::random(16)),
        );
    }
}
