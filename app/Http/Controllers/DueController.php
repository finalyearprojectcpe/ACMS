<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdviserDueRequest;
use App\Http\Requests\WaiveStudentDueRequest;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\EmailDelivery;
use App\Models\PaymentAccount;
use App\Models\StudentDueAssignment;
use App\Services\DashboardAccess;
use App\Services\DueAssignmentService;
use App\Services\DuePaymentService;
use App\Services\DueReceiptService;
use App\Services\PaystackException;
use App\Services\PaystackWebhookService;
use App\Services\Spreadsheet\DuePaymentsWorkbook;
use App\Services\Spreadsheet\SpreadsheetExportService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DueController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly DueAssignmentService $dueAssignments,
        private readonly DueReceiptService $receipts,
    ) {}

    public function store(StoreAdviserDueRequest $request): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSet($advisor, $request->integer('student_set_id'));
        $validated = $request->validated();
        $session = $advisor->academicSessions()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->first() ?? $advisor->academicSessions()->latest('starts_on')->first();
        $isPublishing = $validated['action'] === 'publish';
        $account = PaymentAccount::currentFor($advisor);

        if ($session === null) {
            throw ValidationException::withMessages([
                'student_set_id' => 'Add an academic session before creating a due.',
            ]);
        }

        if ($isPublishing && ! ($account instanceof PaymentAccount)) {
            throw ValidationException::withMessages([
                'payment_account' => 'Connect a Paystack payout account before publishing this due.',
            ]);
        }

        $due = Due::query()->create([
            'advisor_profile_id' => $advisor->id,
            'student_set_id' => $set->id,
            'academic_session_id' => $session->id,
            'payment_account_id' => $account?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount_kobo' => (int) $validated['amount_naira'] * 100,
            'currency' => 'NGN',
            'due_on' => $validated['due_on'] ?? null,
            'status' => $isPublishing ? Due::StatusPublished : Due::StatusDraft,
            'published_at' => $isPublishing ? now() : null,
        ]);

        if ($isPublishing) {
            $this->dueAssignments->syncForDue($due);
        }

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', $isPublishing ? 'Due published for the selected set.' : 'Due draft saved.');
    }

    public function update(StoreAdviserDueRequest $request, Due $due): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsDue($advisor, $due);
        abort_if($due->status === Due::StatusArchived, 404);
        $validated = $request->validated();
        $set = $this->access->studentSet($advisor, $request->integer('student_set_id'));
        $amountKobo = (int) $validated['amount_naira'] * 100;
        $wasPublished = $due->status === Due::StatusPublished;

        if ($wasPublished && (int) $due->student_set_id !== (int) $set->id) {
            throw ValidationException::withMessages([
                'student_set_id' => 'The student set cannot change after a due is published. Create a separate due for another set.',
            ]);
        }

        $isPublishing = ! $wasPublished && $validated['action'] === 'publish';
        $account = PaymentAccount::currentFor($advisor);

        if ($isPublishing && ! ($account instanceof PaymentAccount)) {
            throw ValidationException::withMessages([
                'payment_account' => 'Connect a Paystack payout account before publishing this due.',
            ]);
        }

        DB::transaction(function () use ($due, $set, $validated, $amountKobo, $wasPublished, $isPublishing, $account): void {
            $amountChanged = (int) $due->amount_kobo !== $amountKobo;
            $due->forceFill([
                'student_set_id' => $set->id,
                'payment_account_id' => $account?->id ?? $due->payment_account_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'amount_kobo' => $amountKobo,
                'due_on' => $validated['due_on'] ?? null,
                'status' => $wasPublished || $isPublishing ? Due::StatusPublished : Due::StatusDraft,
                'published_at' => $wasPublished ? $due->published_at : ($isPublishing ? now() : null),
            ])->save();

            if ($isPublishing) {
                $this->dueAssignments->syncForDue($due);
            } elseif ($wasPublished && $amountChanged) {
                $this->dueAssignments->updateAmount($due, $amountKobo);
            }
        });

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', $isPublishing ? 'Due published for the selected set.' : 'Due details updated.');
    }

    public function archive(Request $request, Due $due): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsDue($advisor, $due);
        $this->dueAssignments->archive($due);

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', 'Due archived. Students can no longer start new payments for it.');
    }

    public function restore(Request $request, Due $due): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsDue($advisor, $due);
        abort_unless($due->status === Due::StatusArchived, 404);
        $account = PaymentAccount::currentFor($advisor);

        if (! ($account instanceof PaymentAccount)) {
            throw ValidationException::withMessages([
                'payment_account' => 'Connect an active Paystack payout account before restoring this due.',
            ]);
        }

        $due->forceFill([
            'payment_account_id' => $account->id,
            'status' => Due::StatusPublished,
            'archived_at' => null,
            'published_at' => $due->published_at ?? now(),
        ])->save();
        $this->dueAssignments->syncForDue($due);

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', 'Due restored. Pending students can pay it again.');
    }

    public function waive(
        WaiveStudentDueRequest $request,
        Due $due,
        StudentDueAssignment $studentDueAssignment,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsAssignment($advisor, $due, $studentDueAssignment);
        $this->dueAssignments->waive(
            $studentDueAssignment,
            $request->user(),
            $request->string('reason')->trim()->toString() ?: null,
        );

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', 'Payment requirement waived for this student.');
    }

    public function restoreAssignment(
        Request $request,
        Due $due,
        StudentDueAssignment $studentDueAssignment,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsAssignment($advisor, $due, $studentDueAssignment);
        abort_unless($due->status === Due::StatusPublished, 404);
        $this->dueAssignments->restore($studentDueAssignment);

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', 'Payment requirement restored for this student.');
    }

    public function export(
        Request $request,
        Due $due,
        DuePaymentsWorkbook $workbook,
        SpreadsheetExportService $exports,
    ): StreamedResponse {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsDue($advisor, $due);

        return $exports->download(
            $workbook->build($due),
            Str::lower($advisor->programme_abbreviation).'-'.Str::slug($due->title).'-payments-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function startPayment(
        Request $request,
        StudentDueAssignment $studentDueAssignment,
        DuePaymentService $payments,
    ): RedirectResponse|Response {
        $student = $this->access->student($request);
        abort_unless((int) $studentDueAssignment->student_profile_id === (int) $student->id, 404);
        $studentDueAssignment->loadMissing('due');
        abort_unless($studentDueAssignment->due?->status === Due::StatusPublished, 404);

        try {
            $payment = $payments->startPayment(
                $studentDueAssignment,
                fn (DuePayment $payment): string => route('dashboard.dues.payments.callback', ['duePayment' => $payment]),
            );
        } catch (ConnectionException|PaystackException|\RuntimeException) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('error', 'Payment is temporarily unavailable. Try again shortly or contact your course adviser.');
        }

        if ($payment->status === DuePayment::StatusSuccess) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('success', 'This due has already been paid.');
        }

        return Inertia::location((string) $payment->authorization_url);
    }

    public function callback(
        Request $request,
        DuePayment $duePayment,
        DuePaymentService $payments,
    ): RedirectResponse {
        $student = $this->access->student($request);
        abort_unless((int) $duePayment->student_profile_id === (int) $student->id, 404);
        $reference = $request->string('reference')->toString();
        abort_unless($reference === '' || $reference === $duePayment->reference, 404);

        try {
            $payment = $payments->verifyPayment($duePayment);
        } catch (\Throwable) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('error', 'Payment could not be confirmed yet. If money left your account, check again shortly.');
        }

        return to_route('dashboard.section', ['section' => 'dues'])
            ->with(
                $payment->status === DuePayment::StatusSuccess ? 'success' : 'error',
                $payment->status === DuePayment::StatusSuccess
                    ? 'Payment received. Your receipt is being prepared and will be emailed to you.'
                    : 'Payment was not completed.',
            );
    }

    public function downloadReceipt(Request $request, string $paystackTransactionId): RedirectResponse|StreamedResponse
    {
        $user = $this->access->user($request);
        $payment = DuePayment::query()->where('paystack_transaction_id', $paystackTransactionId)->firstOrFail();
        abort_unless($this->access->canViewPayment($user, $payment), 404);
        abort_unless(in_array($payment->status, [DuePayment::StatusSuccess, DuePayment::StatusReversed], true), 404);

        if (
            $payment->receipt_status !== DuePayment::ReceiptStatusReady
            || ! is_string($payment->receipt_disk)
            || ! is_string($payment->receipt_path)
            || ! Storage::disk($payment->receipt_disk)->exists($payment->receipt_path)
        ) {
            $this->receipts->queue($payment, force: true);

            return back()->with('success', 'Your receipt is being prepared. This page will update when it is ready.');
        }

        return Storage::disk($payment->receipt_disk)->download(
            $payment->receipt_path,
            'receipt-'.$payment->receipt_number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function retryReceipt(Request $request, string $paystackTransactionId): RedirectResponse
    {
        $user = $this->access->user($request);
        $payment = DuePayment::query()->where('paystack_transaction_id', $paystackTransactionId)->firstOrFail();
        abort_unless($this->access->canViewPayment($user, $payment), 404);
        $this->receipts->queue($payment, force: true);

        return back()->with('success', 'Receipt generation restarted. This page will update when it is ready.');
    }

    public function resendReceiptEmail(Request $request, string $paystackTransactionId): RedirectResponse
    {
        $user = $this->access->user($request);
        $payment = DuePayment::query()->where('paystack_transaction_id', $paystackTransactionId)->firstOrFail();
        abort_unless($this->access->canViewPayment($user, $payment), 404);
        abort_unless($payment->receipt_status === DuePayment::ReceiptStatusReady, 404);
        $delivery = $this->receipts->queueEmail($payment, newSendCycle: true);

        return back()->with(
            $delivery instanceof EmailDelivery ? 'success' : 'error',
            $delivery instanceof EmailDelivery ? 'Receipt email queued.' : 'Add an email address before sending the receipt.',
        );
    }

    public function webhook(Request $request, PaystackWebhookService $webhooks): Response
    {
        return response('', $webhooks->process($request->getContent(), $request->header('x-paystack-signature')));
    }
}
