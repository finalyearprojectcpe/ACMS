<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
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

    public function adviserDues(AdvisorProfile $advisor, ?StudentSet $set, ?AcademicSession $selectedSession = null, ?AcademicSessionSemester $selectedSemester = null): array
    {
        $sessions = $set?->academicSessions()
            ->with(['academicYear', 'semesterInstances.semester'])
            ->orderBy('start_year')
            ->get() ?? collect();
        $dues = $set?->dues()
            ->with(['studentSet', 'academicSession', 'academicSessionSemester.semester', 'assignments', 'paymentAccount'])
            ->when($selectedSemester instanceof AcademicSessionSemester, fn ($query) => $query->where('academic_session_semester_id', $selectedSemester->id))
            ->when($selectedSession instanceof AcademicSession && ! $selectedSemester instanceof AcademicSessionSemester, fn ($query) => $query->where('academic_session_id', $selectedSession->id)->whereNull('academic_session_semester_id'))
            ->latest()
            ->get() ?? collect();
        $assignments = $dues->where('status', Due::StatusPublished)
            ->flatMap(fn (Due $due): Collection => $due->assignments);
        $pending = $assignments->where('status', StudentDueAssignment::StatusPending);
        $paid = $assignments->where('status', StudentDueAssignment::StatusPaid);

        return [
            'selectedSet' => $set instanceof StudentSet ? $this->presenter->studentSet($set) : null,
            'sessions' => $sessions->map(fn (AcademicSession $session): array => [
                'id' => (int) $session->id,
                'routeYears' => $session->routeYears(),
                'name' => $session->label(),
                'level' => $session->academicYear->name,
                'semesters' => $session->semesterInstances->sortBy('semester.sort_order')->map(fn (AcademicSessionSemester $semester): array => [
                    'id' => (int) $semester->id,
                    'slug' => $semester->semester->slug,
                    'name' => $semester->semester->name,
                ])->values()->all(),
            ])->all(),
            'selectedPeriod' => $selectedSemester instanceof AcademicSessionSemester
                ? ['kind' => 'semester', 'id' => (int) $selectedSemester->id]
                : ($selectedSession instanceof AcademicSession
                    ? ['kind' => 'session', 'id' => (int) $selectedSession->id]
                    : ['kind' => 'all']),
            'periodLabel' => $selectedSemester instanceof AcademicSessionSemester
                ? $selectedSemester->academicSession->academicYear->name.' · '.$selectedSemester->academicSession->label().' Session · '.$selectedSemester->semester->name
                : ($selectedSession instanceof AcademicSession
                    ? $selectedSession->academicYear->name.' · '.$selectedSession->label().' Session · Whole Session'
                    : 'All Dues'),
            'collectedAmount' => $this->presenter->amount((int) $paid->sum('amount_kobo')),
            'outstandingAmount' => $this->presenter->amount((int) $pending->sum('amount_kobo')),
            'dues' => $dues->map(fn (Due $due): array => $this->dueSummary($due))->all(),
            'paymentSettingsUrl' => route('dashboard.section', ['section' => 'settings']),
            'hasActivePaymentAccount' => PaymentAccount::currentFor($advisor) instanceof PaymentAccount,
        ];
    }

    public function dueForm(AdvisorProfile $advisor, ?Due $due = null, ?string $setPublicId = null, ?string $sessionPublicId = null, ?string $semesterPublicId = null): array
    {
        $selectedSet = $advisor->studentSets()
            ->with(['academicSessions.academicYear', 'academicSessions.semesterInstances.semester'])
            ->when($due?->student_set_id ?? $setPublicId, fn ($query) => $due instanceof Due
                ? $query->whereKey($due->student_set_id)
                : $query->where('public_id', $setPublicId))
            ->first() ?? $advisor->studentSets()
            ->with(['academicSessions.academicYear', 'academicSessions.semesterInstances.semester'])
            ->orderByDesc('entry_start_year')
            ->first();
        $account = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);
        $selectedSession = ($due instanceof Due
            ? $selectedSet?->academicSessions->firstWhere('id', $due->academic_session_id)
            : $selectedSet?->academicSessions->firstWhere('public_id', $sessionPublicId))
            ?? $selectedSet?->academicSessions->firstWhere('academic_year_id', $selectedSet->current_academic_year_id)
            ?? $selectedSet?->academicSessions->sortByDesc('start_year')->first();
        $selectedSemester = $due instanceof Due
            ? $selectedSession?->semesterInstances->firstWhere('id', $due->academic_session_semester_id)
            : $selectedSession?->semesterInstances->firstWhere('public_id', $semesterPublicId);

        return [
            'mode' => $due instanceof Due ? 'edit' : 'create',
            'selectedSetName' => $selectedSet?->label(),
            'sessions' => $selectedSet?->academicSessions->map(fn (AcademicSession $session): array => [
                'id' => (int) $session->id,
                'publicId' => $session->public_id,
                'label' => $session->academicYear->name.' · '.$session->label().' Session',
                'semesters' => $session->semesterInstances->sortBy('semester.sort_order')->map(fn (AcademicSessionSemester $semester): array => [
                    'id' => (int) $semester->id,
                    'publicId' => $semester->public_id,
                    'name' => $semester->semester->name,
                ])->values()->all(),
            ])->values()->all() ?? [],
            'paymentAccounts' => $account instanceof PaymentAccount ? [$this->paymentAccount($account)] : [],
            'paymentSettingsUrl' => route('dashboard.section', ['section' => 'settings']),
            'returnUrl' => $selectedSet instanceof StudentSet
                ? $this->dueListUrl($selectedSet, $due instanceof Due || $sessionPublicId !== null ? $selectedSession : null, $selectedSemester)
                : route('dashboard.section', ['section' => 'student-dues']),
            'defaults' => [
                'studentSetId' => $selectedSet?->id,
                'academicSessionId' => $selectedSession?->id,
                'academicSessionSemesterId' => $selectedSemester?->id,
                'title' => $due?->title ?? '',
                'description' => $due?->description ?? '',
                'amountNaira' => $due instanceof Due ? (string) ((int) $due->amount_kobo / 100) : '',
                'dueOn' => $due?->due_on?->toDateString() ?? now()->addMonth()->toDateString(),
            ],
            'due' => $due instanceof Due ? [
                'id' => (int) $due->id,
                'publicId' => $due->public_id,
                'status' => $this->dueStatusLabel($due->status),
                'canChangePeriod' => $due->status === Due::StatusDraft,
                'canChangeAmount' => $due->status !== Due::StatusArchived,
            ] : null,
        ];
    }

    public function dueDetail(Due $due): array
    {
        $due->loadMissing([
            'studentSet',
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
                'publicId' => $due->public_id,
                'title' => $due->title,
                'amount' => $this->presenter->amount((int) $assignment->amount_kobo),
            ],
            'assignment' => [
                'id' => (int) $assignment->id,
                'publicId' => $assignment->public_id,
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

    public function studentRecordDues(StudentProfile $student): array
    {
        $assignments = $student->dueAssignments()
            ->with(['due.academicSession', 'due.academicSessionSemester.semester', 'latestPayment.receiptEmailDelivery'])
            ->whereHas('due', fn ($query) => $query->whereIn('status', [Due::StatusPublished, Due::StatusArchived]))
            ->latest()
            ->get();

        $rows = $assignments->map(function (StudentDueAssignment $assignment): array {
            $due = $assignment->due;
            $payment = $assignment->status === StudentDueAssignment::StatusPaid
                ? $this->queueReceiptWhenNeeded($assignment->latestPayment)
                : null;

            return [
                'status' => $assignment->status,
                'name' => $due->title,
                'period' => $due->academicSession?->label().' Session'.($due->academicSessionSemester ? ' · '.$due->academicSessionSemester->semester->name : ''),
                'amount' => $this->presenter->amount((int) ($payment?->amount_kobo ?? $assignment->amount_kobo)),
                'assignedAt' => $assignment->created_at?->toFormattedDateString(),
                'paidAt' => $assignment->paid_at?->toFormattedDateString(),
                'waivedAt' => $assignment->waived_at?->toFormattedDateString(),
                'waiverReason' => $assignment->waiver_reason,
                'receiptStatus' => $payment?->receipt_status,
                'receiptUrl' => $this->receipt($payment)['receiptUrl'],
            ];
        });

        return [
            'unpaid' => $rows->where('status', StudentDueAssignment::StatusPending)->values()->all(),
            'paid' => $rows->where('status', StudentDueAssignment::StatusPaid)->values()->all(),
            'waived' => $rows->where('status', StudentDueAssignment::StatusWaived)->values()->all(),
        ];
    }

    public function paymentSettings(AdvisorProfile $advisor): array
    {
        $account = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);

        return [
            'accounts' => $account instanceof PaymentAccount ? [$this->paymentAccount($account)] : [],
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
        ];
    }

    private function dueSummary(Due $due): array
    {
        $due->loadMissing(['studentSet', 'academicSession.academicYear', 'academicSessionSemester.semester', 'assignments', 'paymentAccount']);
        $paid = $due->assignments->where('status', StudentDueAssignment::StatusPaid);
        $pending = $due->assignments->where('status', StudentDueAssignment::StatusPending);
        $waived = $due->assignments->where('status', StudentDueAssignment::StatusWaived);

        return [
            'id' => (int) $due->id,
            'publicId' => $due->public_id,
            'title' => $due->title,
            'description' => $due->description,
            'amount' => $this->presenter->amount((int) $due->amount_kobo),
            'status' => $this->dueStatusLabel($due->status),
            'set' => $due->studentSet->label(),
            'period' => $due->academicSession->academicYear->name.' · '.$due->academicSession->label().' Session'.($due->academicSessionSemester ? ' · '.$due->academicSessionSemester->semester->name : ' · Whole Session'),
            'periodParts' => [
                'level' => $due->academicSession->academicYear->name,
                'levelNumber' => (int) $due->academicSession->academicYear->year_number,
                'session' => $due->academicSession->label().' Session',
                'sessionStartYear' => (int) $due->academicSession->start_year,
                'semester' => $due->academicSessionSemester?->semester->name ?? 'Whole Session',
                'semesterOrder' => (int) ($due->academicSessionSemester?->semester->sort_order ?? 0),
            ],
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
            'listUrl' => $this->dueListUrl($due->studentSet, $due->academicSession, $due->academicSessionSemester),
        ];
    }

    private function dueListUrl(StudentSet $set, ?AcademicSession $session, ?AcademicSessionSemester $semester): string
    {
        if ($semester instanceof AcademicSessionSemester && $session instanceof AcademicSession) {
            return route('student-sets.dues.semester', [
                ...$set->routeParameters(),
                'sessionYears' => $session->routeYears(),
                'semester' => $semester->semester->slug,
            ]);
        }

        if ($session instanceof AcademicSession) {
            return route('student-sets.dues.session', [
                ...$set->routeParameters(),
                'sessionYears' => $session->routeYears(),
            ]);
        }

        return route('student-sets.dues.show', $set->routeParameters());
    }

    private function dueStudent(StudentDueAssignment $assignment): array
    {
        $payment = $this->queueReceiptWhenNeeded($assignment->latestPayment);

        return [
            'id' => (int) $assignment->id,
            'publicId' => $assignment->public_id,
            'studentName' => $assignment->studentProfile->user->name,
            'studentAvatar' => $assignment->studentProfile->user->profilePhotoUrl(),
            'registrationNumber' => $assignment->studentProfile->registration_number,
            'amount' => $this->presenter->amount((int) ($payment?->amount_kobo ?? $assignment->amount_kobo)),
            'status' => match ($assignment->status) {
                StudentDueAssignment::StatusPaid => 'Paid',
                StudentDueAssignment::StatusWaived => 'Waived',
                default => 'Unpaid',
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
        $payment = $this->queueReceiptWhenNeeded($assignment->latestPayment);

        return [
            'id' => (int) $assignment->id,
            'name' => $assignment->due->title,
            'description' => $assignment->due->description,
            'amount' => $this->presenter->amount((int) ($payment?->amount_kobo ?? $assignment->amount_kobo)),
            'paidAt' => $assignment->paid_at?->toFormattedDateString() ?? 'Paid',
            ...$this->receipt($payment),
        ];
    }

    private function queueReceiptWhenNeeded(?DuePayment $payment): ?DuePayment
    {
        if ($payment instanceof DuePayment
            && $payment->receipt_status === null
            && $payment->paystack_transaction_id !== null) {
            return $this->receipts->queue($payment);
        }

        return $payment;
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
                'error' => 'Banks are unavailable right now. Try again in a moment.',
            ];
        }

        return $options === []
            ? [
                'options' => [],
                'error' => 'Banks are unavailable right now. Try again in a moment.',
            ]
            : ['options' => $options, 'error' => null];
    }
}
