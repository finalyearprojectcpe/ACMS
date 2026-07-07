<?php

namespace App\Services;

use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DuePaymentService
{
    public function __construct(private readonly PaystackService $paystack) {}

    /**
     * @param  callable(DuePayment): string  $callbackUrl
     */
    public function startPayment(StudentDueAssignment $assignment, callable $callbackUrl): DuePayment
    {
        $assignment->loadMissing(['due.paymentAccount', 'studentProfile.user']);

        if ($assignment->status === StudentDueAssignment::StatusPaid) {
            return $assignment->latestPayment instanceof DuePayment
                ? $assignment->latestPayment
                : $assignment->payments()->where('status', DuePayment::StatusSuccess)->latest()->firstOrFail();
        }

        $payment = $assignment->payments()->create([
            'student_profile_id' => $assignment->student_profile_id,
            'school_id' => $assignment->studentProfile->school_id,
            'payment_account_id' => $assignment->due->payment_account_id,
            'provider' => DuePayment::ProviderPaystack,
            'reference' => $this->newReference($assignment),
            'status' => DuePayment::StatusInitialized,
            'amount_kobo' => $assignment->amount_kobo,
            'currency' => $assignment->due->currency,
        ]);

        $paystackData = $this->paystack->initializeTransaction($payment, $callbackUrl($payment));

        $payment->forceFill([
            'authorization_url' => Arr::get($paystackData, 'authorization_url'),
            'access_code' => Arr::get($paystackData, 'access_code'),
            'provider_payload' => $paystackData,
        ])->save();

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
        return DB::transaction(function () use ($payment, $data): DuePayment {
            $payment = DuePayment::query()
                ->with('assignment')
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $status = (string) Arr::get($data, 'status', DuePayment::StatusFailed);
            $amount = (int) Arr::get($data, 'amount', 0);
            $currency = (string) Arr::get($data, 'currency', $payment->currency);

            if ($amount !== (int) $payment->amount_kobo || strtoupper($currency) !== strtoupper($payment->currency)) {
                $payment->forceFill([
                    'status' => DuePayment::StatusFailed,
                    'gateway_response' => 'Amount or currency mismatch',
                    'provider_payload' => $data,
                ])->save();

                throw new RuntimeException('Paystack returned a payment that does not match this due.');
            }

            $paidAt = Arr::get($data, 'paid_at') ?? Arr::get($data, 'paidAt');
            $payment->forceFill([
                'status' => $status,
                'paystack_transaction_id' => (string) Arr::get($data, 'id', $payment->paystack_transaction_id),
                'channel' => Arr::get($data, 'channel'),
                'fees_kobo' => Arr::get($data, 'fees'),
                'gateway_response' => Arr::get($data, 'gateway_response'),
                'paid_at' => $status === DuePayment::StatusSuccess && is_string($paidAt) ? $paidAt : $payment->paid_at,
                'provider_payload' => $data,
            ])->save();

            if ($status === DuePayment::StatusSuccess) {
                $receiptNumber = $payment->receipt_number
                    ?? sprintf('GM-%s-%06d', now()->format('Ymd'), $payment->id);

                $payment->forceFill([
                    'receipt_number' => $receiptNumber,
                    'paid_at' => $payment->paid_at ?? now(),
                ])->save();

                $payment->assignment->forceFill([
                    'status' => StudentDueAssignment::StatusPaid,
                    'paid_at' => $payment->paid_at,
                    'latest_due_payment_id' => $payment->id,
                ])->save();
            }

            return $payment->fresh(['assignment.due', 'studentProfile.user']);
        });
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
