<?php

use App\Jobs\GenerateDuePaymentReceipt;
use App\Jobs\SendEmailDelivery;
use App\Mail\DuePaymentReceiptMail;
use App\Models\AcademicSession;
use App\Models\AdvisorProfile;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\PaymentAccount;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
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
        ->and($payment->receiptEmailDelivery->metadata['attachment']['path'])->toBe($payment->receipt_path)
        ->and((new DuePaymentReceiptMail($payment->receiptEmailDelivery))->render())
        ->toContain($fixture['advisorName']);
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
            ->where('receipt.amount', '₦15,000.00')
            ->etc());
});

test('a generated receipt remains ready when its email cannot be queued', function () {
    Queue::fake();
    Storage::fake('local');
    Config::set('filesystems.due_receipt_disk', 'local');
    $fixture = createDueReceiptFixture();

    $payment = app(DuePaymentService::class)->recordPaystackData(
        $fixture['payment'],
        successfulPaystackData($fixture),
    );
    $receipts = Mockery::mock(DueReceiptService::class);
    $receipts->shouldReceive('queueEmail')
        ->once()
        ->andThrow(new RuntimeException('Email queue unavailable.'));

    (new GenerateDuePaymentReceipt($payment->id))->handle(
        app(ReceiptPdfService::class),
        $receipts,
    );

    $payment->refresh();

    expect($payment->receipt_status)->toBe(DuePayment::ReceiptStatusReady)
        ->and($payment->receipt_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($payment->receipt_path))->toBeTrue()
        ->and($payment->receipt_generation_error)->toBeNull();
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
 * @return array{studentUser: User, payment: DuePayment, assignment: StudentDueAssignment, due: Due, advisorProfileId: int, advisorName: string, paymentAccountId: int}
 */
function createDueReceiptFixture(): array
{
    $advisor = AdvisorProfile::factory()->create();
    $set = StudentSet::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'department_name' => 'Computer Engineering',
        'department_slug' => 'computer-engineering',
        'entry_start_year' => 2025,
        'entry_end_year' => 2026,
    ]);
    $currentSession = AcademicSession::factory()->create([
        'student_set_id' => $set->id,
        'start_year' => 2025,
        'end_year' => 2026,
    ]);
    $studentUser = User::factory()->student()->create([
        'name' => 'Chinaza Okafor',
        'email' => 'chinaza@example.test',
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'user_id' => $studentUser->id,
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'registration_number' => '2023001234',
    ]);
    $paymentAccount = PaymentAccount::factory()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'paystack_subaccount_code' => 'ACCT_RECEIPT_TEST',
        'status' => PaymentAccount::StatusActive,
        'active_marker' => 1,
    ]);
    $due = Due::factory()->create([
        'advisor_profile_id' => $set->advisor_profile_id,
        'student_set_id' => $set->id,
        'academic_session_id' => $currentSession->id,
        'payment_account_id' => $paymentAccount->id,
        'title' => 'Student Dues 300 Level',
        'amount_kobo' => 1500000,
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
        'advisor_profile_id' => $set->advisor_profile_id,
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
        'advisorProfileId' => (int) $set->advisor_profile_id,
        'advisorName' => $advisor->user->displayName(),
        'paymentAccountId' => (int) $paymentAccount->id,
    ];
}

/**
 * @param  array{payment: DuePayment, assignment: StudentDueAssignment, due: Due, advisorProfileId: int, advisorName: string, paymentAccountId: int}  $fixture
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
            'advisor_profile_id' => $fixture['advisorProfileId'],
            'due_id' => $fixture['due']->id,
            'student_due_assignment_id' => $fixture['assignment']->id,
            'student_profile_id' => $fixture['assignment']->student_profile_id,
            'payment_account_id' => $fixture['paymentAccountId'],
        ],
    ];
}
