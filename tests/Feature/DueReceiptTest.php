<?php

use App\Jobs\GenerateDuePaymentReceipt;
use App\Jobs\SendEmailDelivery;
use App\Mail\DuePaymentReceiptMail;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\PaymentAccount;
use App\Models\StaffProfile;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\DueAssignmentService;
use App\Services\DuePaymentService;
use App\Services\DueReceiptService;
use App\Services\EmailDeliveryService;
use App\Services\ReceiptPdfService;
use App\Services\ReceiptUrlService;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('confirmed Paystack payments queue, store, email, verify, and download PDF receipts', function () {
    Queue::fake();
    Storage::fake('local');
    Config::set('filesystems.due_receipt_disk', 'local');
    $fixture = createDueReceiptFixture();

    $payment = app(DuePaymentService::class)->recordPaystackData(
        $fixture['payment'],
        successfulPaystackData($fixture),
    );

    expect($payment->status)->toBe(DuePayment::StatusSuccess)
        ->and($payment->paystack_transaction_id)->toBe('987654321')
        ->and($payment->receipt_status)->toBe(DuePayment::ReceiptStatusPending);
    Queue::assertPushed(GenerateDuePaymentReceipt::class);

    (new GenerateDuePaymentReceipt($payment->id))->handle(
        app(ReceiptPdfService::class),
        app(DueReceiptService::class),
    );

    $payment->refresh()->load('receiptEmailDelivery');

    expect($payment->receipt_status)->toBe(DuePayment::ReceiptStatusReady)
        ->and($payment->receipt_path)->not->toBeNull()
        ->and(Storage::disk('local')->get($payment->receipt_path))->toStartWith('%PDF')
        ->and($payment->receiptEmailDelivery)->not->toBeNull()
        ->and($payment->receiptEmailDelivery->metadata['attachment']['path'])->toBe($payment->receipt_path);
    Queue::assertPushed(SendEmailDelivery::class);

    Config::set('services.resend.key', 're_test_key');
    Mail::fake();

    app(EmailDeliveryService::class)->deliver($payment->receiptEmailDelivery);

    Mail::assertSent(DuePaymentReceiptMail::class, function (DuePaymentReceiptMail $mail) use ($payment): bool {
        $recipient = $payment->studentProfile->user->email;

        return $recipient !== null
            && $mail->hasTo($recipient)
            && $mail->hasAttachment(
                Attachment::fromStorageDisk('local', (string) $payment->receipt_path)
                    ->as('receipt-'.$payment->receipt_number.'.pdf')
                    ->withMime('application/pdf'),
            );
    });

    $download = $this->actingAs($fixture['studentUser'])->get(route('dashboard.dues.payments.receipt', [
        'paystackTransactionId' => $payment->paystack_transaction_id,
    ]));

    $download->assertOk()->assertDownload('receipt-'.$payment->receipt_number.'.pdf');
    expect($download->streamedContent())->toStartWith('%PDF');

    $verificationUrl = app(ReceiptUrlService::class)->verificationUrl($payment);
    $verificationPath = parse_url($verificationUrl, PHP_URL_PATH).'?'.parse_url($verificationUrl, PHP_URL_QUERY);
    Config::set('inertia.ssr.enabled', false);

    $this->get($verificationPath)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('receipt-verification')
            ->where('receipt.status', 'valid')
            ->where('receipt.transactionId', '987654321')
            ->where('receipt.amount', 'NGN 15,000.00')
            ->etc());
});

test('a late completion keeps the exact older checkout amount after a due amount changes', function () {
    Queue::fake();
    $fixture = createDueReceiptFixture();
    $assignment = $fixture['assignment'];
    $due = $fixture['due'];

    $due->forceFill(['amount_kobo' => 1600000])->save();
    app(DueAssignmentService::class)->updateAmount($due, 1600000);

    expect($assignment->refresh()->amount_kobo)->toBe(1600000)
        ->and($fixture['payment']->refresh()->status)->toBe(DuePayment::StatusAbandoned);

    $payment = app(DuePaymentService::class)->recordPaystackData(
        $fixture['payment'],
        successfulPaystackData($fixture),
    );

    expect($payment->status)->toBe(DuePayment::StatusSuccess)
        ->and($payment->amount_kobo)->toBe(1500000)
        ->and($assignment->refresh()->amount_kobo)->toBe(1500000)
        ->and($assignment->status)->toBe(StudentDueAssignment::StatusPaid);
});

/**
 * @return array{studentUser: User, payment: DuePayment, assignment: StudentDueAssignment, due: Due, schoolId: int, paymentAccountId: int}
 */
function createDueReceiptFixture(): array
{
    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $studentUser = User::factory()->student()->create([
        'school_id' => $school->id,
        'name' => 'Chinaza Okafor',
        'email' => 'chinaza@example.test',
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => AcademicYear::factory(),
        'entry_academic_session_id' => $entrySession->id,
        'registration_number' => '2023001234',
    ]);
    $staffUser = User::factory()->staff()->create(['school_id' => $school->id]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);
    $paymentAccount = PaymentAccount::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'staff_profile_id' => $staffProfile->id,
        'entry_academic_session_id' => null,
        'scope_key' => 'staff-'.$staffProfile->id,
        'paystack_subaccount_code' => 'ACCT_RECEIPT_TEST',
        'status' => PaymentAccount::StatusActive,
        'active_marker' => 1,
    ]);
    $due = Due::query()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'academic_session_id' => $currentSession->id,
        'created_by_staff_profile_id' => $staffProfile->id,
        'payment_account_id' => $paymentAccount->id,
        'title' => 'Departmental Dues 300 Level',
        'amount_kobo' => 1500000,
        'currency' => 'NGN',
        'status' => Due::StatusPublished,
        'published_at' => now(),
    ]);
    $assignment = StudentDueAssignment::query()->create([
        'due_id' => $due->id,
        'student_profile_id' => $studentProfile->id,
        'amount_kobo' => 1500000,
        'status' => StudentDueAssignment::StatusPending,
    ]);
    $payment = DuePayment::query()->create([
        'student_due_assignment_id' => $assignment->id,
        'student_profile_id' => $studentProfile->id,
        'school_id' => $school->id,
        'payment_account_id' => $paymentAccount->id,
        'provider' => DuePayment::ProviderPaystack,
        'reference' => 'DUE-RECEIPT-TEST',
        'status' => DuePayment::StatusInitialized,
        'amount_kobo' => 1500000,
        'currency' => 'NGN',
        'authorization_url' => 'https://checkout.paystack.com/receipt-test',
        'provider_payload' => [
            'payout_account' => ['subaccount_code' => $paymentAccount->paystack_subaccount_code],
        ],
    ]);

    return [
        'studentUser' => $studentUser,
        'payment' => $payment,
        'assignment' => $assignment,
        'due' => $due,
        'schoolId' => (int) $school->id,
        'paymentAccountId' => (int) $paymentAccount->id,
    ];
}

/**
 * @param  array{payment: DuePayment, assignment: StudentDueAssignment, due: Due, schoolId: int, paymentAccountId: int}  $fixture
 * @return array<string, mixed>
 */
function successfulPaystackData(array $fixture): array
{
    return [
        'id' => 987654321,
        'status' => DuePayment::StatusSuccess,
        'reference' => $fixture['payment']->reference,
        'amount' => 1500000,
        'currency' => 'NGN',
        'channel' => 'card',
        'fees' => 20000,
        'gateway_response' => 'Successful',
        'paid_at' => '2026-07-11T10:00:00+01:00',
        'metadata' => [
            'school_id' => $fixture['schoolId'],
            'due_id' => $fixture['due']->id,
            'student_due_assignment_id' => $fixture['assignment']->id,
            'student_profile_id' => $fixture['assignment']->student_profile_id,
            'payment_account_id' => $fixture['paymentAccountId'],
        ],
    ];
}
