<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\PaymentAccount;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Support\Collection;

class DueDashboardData
{
    public function __construct(
        private readonly DueReceiptService $receipts,
        private readonly PaystackService $paystack,
        private readonly DashboardPresenter $presenter,
    ) {}

    public function adviserDues(AdvisorProfile $advisor, Collection $sets): array
    {
        $dues = $advisor->dues()
            ->with(['studentSet.entryAcademicSession', 'assignments.latestPayment', 'paymentAccount'])
            ->latest()
            ->get();
        $assignments = $dues
            ->where('status', Due::StatusPublished)
            ->flatMap(fn (Due $due): Collection => $due->assignments);
        $pending = $assignments->where('status', StudentDueAssignment::StatusPending);
        $paid = $assignments->where('status', StudentDueAssignment::StatusPaid);

        return [
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->presenter->studentSet($set))->all(),
            'metrics' => [
                'expectedAmount' => $this->presenter->amount((int) $assignments->sum('amount_kobo')),
                'collectedAmount' => $this->presenter->amount((int) $paid->sum('amount_kobo')),
                'outstandingAmount' => $this->presenter->amount((int) $pending->sum('amount_kobo')),
                'studentsOwing' => $pending->pluck('student_profile_id')->unique()->count(),
            ],
            'dues' => $dues->map(fn (Due $due): array => $this->dueSummary($due))->all(),
            'createUrl' => route('dashboard.dues.create'),
            'paymentSettingsUrl' => route('dashboard.section', ['section' => 'payment-settings']),
            'hasActivePaymentAccount' => PaymentAccount::currentFor($advisor) instanceof PaymentAccount,
        ];
    }

    public function dueForm(AdvisorProfile $advisor, ?Due $due = null): array
    {
        $sets = $advisor->studentSets()
            ->with('entryAcademicSession')
            ->orderByDesc('entry_academic_session_id')
            ->get();
        $account = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);

        return [
            'mode' => $due instanceof Due ? 'edit' : 'create',
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->presenter->studentSet($set))->all(),
            'paymentAccounts' => $account instanceof PaymentAccount ? [$this->paymentAccount($account)] : [],
            'paymentSettingsUrl' => route('dashboard.section', ['section' => 'payment-settings']),
            'defaults' => [
                'studentSetId' => $due?->student_set_id ?? $sets->first()?->id,
                'title' => $due?->title ?? '',
                'description' => $due?->description ?? '',
                'amountNaira' => $due instanceof Due ? (string) ((int) $due->amount_kobo / 100) : '',
                'dueOn' => $due?->due_on?->toDateString() ?? now()->addMonth()->toDateString(),
            ],
            'due' => $due instanceof Due ? [
                'id' => (int) $due->id,
                'status' => $this->dueStatusLabel($due->status),
                'canChangeSet' => $due->status === Due::StatusDraft,
                'canChangeAmount' => $due->status !== Due::StatusArchived,
            ] : null,
        ];
    }

    public function dueDetail(Due $due): array
    {
        $due->loadMissing([
            'studentSet.entryAcademicSession',
            'paymentAccount',
            'assignments.studentProfile.user',
            'assignments.latestPayment.receiptEmailDelivery',
        ]);

        return [
            'due' => $this->dueSummary($due),
            'students' => $due->assignments
                ->sortBy(fn (StudentDueAssignment $assignment): string => $assignment->studentProfile->user->name)
                ->values()
                ->map(fn (StudentDueAssignment $assignment): array => $this->dueStudent($assignment))
                ->all(),
        ];
    }

    public function waiverForm(Due $due, StudentDueAssignment $assignment): array
    {
        $assignment->loadMissing('studentProfile.user');

        return [
            'due' => [
                'id' => (int) $due->id,
                'title' => $due->title,
                'amount' => $this->presenter->amount((int) $assignment->amount_kobo),
            ],
            'assignment' => [
                'id' => (int) $assignment->id,
                'studentName' => $assignment->studentProfile->user->name,
                'studentAvatar' => $assignment->studentProfile->user->profilePhotoUrl(),
                'registrationNumber' => $assignment->studentProfile->registration_number,
            ],
        ];
    }

    public function studentDues(StudentProfile $student): array
    {
        $assignments = $student->dueAssignments()
            ->with(['due.paymentAccount', 'latestPayment.receiptEmailDelivery', 'payments'])
            ->whereHas('due', fn ($query) => $query->where('status', Due::StatusPublished))
            ->latest()
            ->get();
        $pending = $assignments->where('status', StudentDueAssignment::StatusPending);
        $paid = $assignments->where('status', StudentDueAssignment::StatusPaid);

        return [
            'pendingAmount' => $this->presenter->amount((int) $pending->sum('amount_kobo')),
            'paidAmount' => $this->presenter->amount((int) $paid->sum(
                fn (StudentDueAssignment $assignment): int => (int) ($assignment->latestPayment?->amount_kobo
                    ?? $assignment->amount_kobo),
            )),
            'totalDues' => $assignments->count(),
            'pending' => $pending
                ->map(fn (StudentDueAssignment $assignment): array => $this->studentPendingDue($assignment))
                ->values()
                ->all(),
            'paid' => $paid
                ->map(fn (StudentDueAssignment $assignment): array => $this->studentPaidDue($assignment))
                ->values()
                ->all(),
        ];
    }

    public function paymentSettings(AdvisorProfile $advisor): array
    {
        $account = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);

        return [
            'accounts' => $account instanceof PaymentAccount ? [$this->paymentAccount($account)] : [],
            'dashboardUrl' => $this->paystackDashboardUrl(),
            'createUrl' => route('dashboard.payment-settings.create'),
        ];
    }

    public function paymentAccountForm(AdvisorProfile $advisor): array
    {
        $account = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);
        $banks = $this->paystackBankOptions();

        return [
            'accounts' => $account instanceof PaymentAccount ? [$this->paymentAccount($account)] : [],
            'bankOptions' => $banks['options'],
            'bankOptionsError' => $banks['error'],
            'dashboardUrl' => $this->paystackDashboardUrl(),
            'returnUrl' => route('dashboard.section', ['section' => 'payment-settings']),
        ];
    }

    private function dueSummary(Due $due): array
    {
        $due->loadMissing(['studentSet.entryAcademicSession', 'assignments', 'paymentAccount']);
        $paid = $due->assignments->where('status', StudentDueAssignment::StatusPaid);
        $pending = $due->assignments->where('status', StudentDueAssignment::StatusPending);
        $waived = $due->assignments->where('status', StudentDueAssignment::StatusWaived);

        return [
            'id' => (int) $due->id,
            'title' => $due->title,
            'description' => $due->description,
            'amount' => $this->presenter->amount((int) $due->amount_kobo),
            'status' => $this->dueStatusLabel($due->status),
            'set' => $due->studentSet->name,
            'dueDate' => $due->due_on?->toFormattedDateString(),
            'paidCount' => $paid->count(),
            'pendingCount' => $pending->count(),
            'waivedCount' => $waived->count(),
            'collectedAmount' => $this->presenter->amount((int) $paid->sum('amount_kobo')),
            'outstandingAmount' => $this->presenter->amount((int) $pending->sum('amount_kobo')),
            'paymentAccount' => $due->paymentAccount instanceof PaymentAccount
                ? $this->paymentAccount($due->paymentAccount)
                : null,
            'detailUrl' => route('dashboard.dues.show', ['due' => $due]),
        ];
    }

    private function dueStudent(StudentDueAssignment $assignment): array
    {
        $payment = $assignment->latestPayment;

        if ($payment instanceof DuePayment
            && $payment->receipt_status === null
            && $payment->paystack_transaction_id !== null) {
            $payment = $this->receipts->queue($payment);
        }

        return [
            'id' => (int) $assignment->id,
            'studentName' => $assignment->studentProfile->user->name,
            'studentAvatar' => $assignment->studentProfile->user->profilePhotoUrl(),
            'registrationNumber' => $assignment->studentProfile->registration_number,
            'amount' => $this->presenter->amount((int) $assignment->amount_kobo),
            'status' => match ($assignment->status) {
                StudentDueAssignment::StatusPaid => 'Paid',
                StudentDueAssignment::StatusWaived => 'Waived',
                default => 'Pending',
            },
            'paidAt' => $assignment->paid_at?->toFormattedDateString(),
            'waiverReason' => $assignment->waiver_reason,
            ...$this->receipt($payment),
        ];
    }

    private function studentPendingDue(StudentDueAssignment $assignment): array
    {
        $account = $assignment->due->paymentAccount;
        $canPay = $account?->isActive() === true;
        $hasCheckout = $canPay && $assignment->payments->contains(
            fn (DuePayment $payment): bool => $payment->status === DuePayment::StatusInitialized
                && is_string($payment->authorization_url)
                && $payment->authorization_url !== ''
                && $payment->created_at?->isAfter(now()->subMinutes(30)) === true
                && data_get($payment->provider_payload, 'payout_account.subaccount_code')
                    === $account?->paystack_subaccount_code,
        );

        return [
            'id' => (int) $assignment->id,
            'name' => $assignment->due->title,
            'description' => $assignment->due->description,
            'amount' => $this->presenter->amount((int) $assignment->amount_kobo),
            'dueDate' => $assignment->due->due_on?->toFormattedDateString(),
            'paymentUrl' => $canPay
                ? route('dashboard.dues.payments.store', ['studentDueAssignment' => $assignment])
                : null,
            'paymentActionLabel' => $hasCheckout ? 'Continue payment' : 'Pay now',
            'paymentUnavailableReason' => $canPay
                ? null
                : 'Ask your course adviser to reconnect the payout account.',
        ];
    }

    private function studentPaidDue(StudentDueAssignment $assignment): array
    {
        $payment = $assignment->latestPayment;

        if ($payment instanceof DuePayment
            && $payment->receipt_status === null
            && $payment->paystack_transaction_id !== null) {
            $payment = $this->receipts->queue($payment);
        }

        return [
            'id' => (int) $assignment->id,
            'name' => $assignment->due->title,
            'description' => $assignment->due->description,
            'amount' => $this->presenter->amount((int) ($payment?->amount_kobo ?? $assignment->amount_kobo)),
            'paidAt' => $assignment->paid_at?->toFormattedDateString() ?? 'Paid',
            ...$this->receipt($payment),
        ];
    }

    private function receipt(?DuePayment $payment): array
    {
        if (! $payment instanceof DuePayment || $payment->paystack_transaction_id === null) {
            return [
                'receiptStatus' => null,
                'receiptUrl' => null,
                'receiptRetryUrl' => null,
                'receiptEmailStatus' => null,
                'receiptEmailRetryUrl' => null,
            ];
        }

        $emailStatus = $payment->receiptEmailDelivery?->status;

        return [
            'receiptStatus' => $payment->receipt_status,
            'receiptUrl' => $payment->receipt_status === DuePayment::ReceiptStatusReady
                ? route('dashboard.dues.payments.receipt', [
                    'paystackTransactionId' => $payment->paystack_transaction_id,
                ])
                : null,
            'receiptRetryUrl' => $payment->receipt_status === DuePayment::ReceiptStatusFailed
                ? route('dashboard.dues.payments.receipt.retry', [
                    'paystackTransactionId' => $payment->paystack_transaction_id,
                ])
                : null,
            'receiptEmailStatus' => $emailStatus,
            'receiptEmailRetryUrl' => in_array($emailStatus, ['failed', 'skipped'], true)
                ? route('dashboard.dues.payments.receipt.email', [
                    'paystackTransactionId' => $payment->paystack_transaction_id,
                ])
                : null,
        ];
    }

    private function paymentAccount(PaymentAccount $account): array
    {
        return [
            'id' => (int) $account->id,
            'label' => $account->label,
            'businessName' => $account->business_name,
            'bankName' => $account->bank_name,
            'accountName' => $account->account_name,
            'accountNumber' => $account->account_number,
            'status' => match ($account->status) {
                PaymentAccount::StatusActive => 'Active',
                PaymentAccount::StatusDisabled => 'Disabled',
                default => 'Draft',
            },
            'set' => 'All student dues',
            'entryAcademicSessionId' => null,
            'verifiedAt' => $account->verified_at?->toFormattedDateString(),
        ];
    }

    private function dueStatusLabel(string $status): string
    {
        return match ($status) {
            Due::StatusPublished => 'Published',
            Due::StatusArchived => 'Archived',
            default => 'Draft',
        };
    }

    /** @return array{options: array<int, array{code: string, name: string}>, error: string|null} */
    private function paystackBankOptions(): array
    {
        try {
            $options = $this->paystack->nigerianBankOptions();
        } catch (\Throwable) {
            return [
                'options' => [],
                'error' => 'Paystack banks could not be loaded. Check the Paystack keys or try again shortly.',
            ];
        }

        return $options === []
            ? [
                'options' => [],
                'error' => 'Paystack did not return any Nigerian banks. Try again shortly.',
            ]
            : ['options' => $options, 'error' => null];
    }

    private function paystackDashboardUrl(): string
    {
        $url = config('services.paystack.dashboard_url');

        return is_string($url) && $url !== '' ? $url : 'https://dashboard.paystack.com';
    }
}
