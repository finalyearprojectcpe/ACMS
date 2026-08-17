<?php

use App\EmailDeliveryStatus;
use App\Jobs\SendEmailDelivery;
use App\Mail\AccessCodeMail;
use App\Models\EmailDelivery;
use App\Models\School;
use App\Models\User;
use App\Services\EmailDeliveryService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

test('access code email delivery is queued with a stable idempotency key', function () {
    Queue::fake();
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $delivery = EmailDelivery::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'status' => EmailDeliveryStatus::Failed->value,
    ]);
    $idempotencyKey = $delivery->idempotency_key;

    app(EmailDeliveryService::class)->sendAccessCode($delivery, 'CPE-STAFF-123456');

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Queued->value)
        ->and($delivery->mailer)->toBe('resend')
        ->and($delivery->idempotency_key)->toBe($idempotencyKey)
        ->and($delivery->metadata['template'])->toBe('access_code')
        ->and($delivery->metadata['access_code'])->toBe('CPE-STAFF-123456');

    Queue::assertPushed(
        SendEmailDelivery::class,
        fn (SendEmailDelivery $job): bool => $job->emailDeliveryId === $delivery->id,
    );
});

test('configured email delivery sends a Laravel mailable', function () {
    Mail::fake();
    Config::set('services.resend.key', 're_test_key');
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $delivery = EmailDelivery::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'recipient_email' => 'lecturer@example.test',
        'subject' => 'GradeManager staff access code',
        'metadata' => [
            'template' => 'access_code',
            'access_code' => 'CPE-STAFF-123456',
            'login_url' => 'https://futo.example.test',
        ],
    ]);

    app(EmailDeliveryService::class)->deliver($delivery);

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Sent->value)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->sent_at)->not->toBeNull();

    Mail::assertSent(AccessCodeMail::class, function (AccessCodeMail $mail): bool {
        return $mail->hasTo('lecturer@example.test')
            && $mail->hasSubject('GradeManager staff access code')
            && $mail->hasMetadata('delivery_id', $mail->delivery->id)
            && $mail->headers()->text['Resend-Idempotency-Key'] === $mail->delivery->idempotency_key
            && str_contains($mail->render(), 'CPE-STAFF-123456');
    });
});

test('an explicit resend starts a new idempotent send cycle', function () {
    Queue::fake();
    $delivery = EmailDelivery::factory()->create([
        'status' => EmailDeliveryStatus::Failed->value,
        'provider_message_id' => 'old-resend-message',
        'provider_event_at' => now(),
        'failed_at' => now(),
    ]);
    $originalKey = $delivery->idempotency_key;

    app(EmailDeliveryService::class)->sendDuePaymentReceipt(
        $delivery,
        dueName: 'Departmental Dues 300L',
        amount: 'NGN 15,000.00',
        receiptNumber: 'GM-20260806-000001',
        downloadUrl: 'https://futo.example.test/receipt',
        disk: 'local',
        path: 'receipts/test.pdf',
        newSendCycle: true,
    );

    expect($delivery->refresh()->idempotency_key)->not->toBe($originalKey)
        ->and($delivery->provider_message_id)->toBeNull()
        ->and($delivery->provider_event_at)->toBeNull()
        ->and($delivery->failed_at)->toBeNull()
        ->and($delivery->status)->toBe(EmailDeliveryStatus::Queued->value);
});

test('a provider outcome is not overwritten by a late delivery job', function () {
    Mail::fake();
    Config::set('services.resend.key', 're_test_key');
    $delivery = EmailDelivery::factory()->create([
        'status' => EmailDeliveryStatus::Bounced->value,
        'provider_message_id' => 'resend-message-123',
        'provider_event_at' => now(),
        'failed_at' => now(),
        'error' => 'The recipient address rejected the email.',
    ]);

    app(EmailDeliveryService::class)->deliver($delivery);

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Bounced->value)
        ->and($delivery->provider_message_id)->toBe('resend-message-123')
        ->and($delivery->error)->toBe('The recipient address rejected the email.');
    Mail::assertNothingSent();
});

test('signed Resend webhooks update delivery outcomes and ignore older events', function () {
    $delivery = EmailDelivery::factory()->create([
        'status' => EmailDeliveryStatus::Sent->value,
        'provider_message_id' => 'resend-message-123',
        'provider_event_at' => now()->subMinute(),
    ]);
    $secret = 'webhook-secret-for-tests';
    Config::set('resend.webhook.secret', 'whsec_'.base64_encode($secret));

    $deliveredPayload = [
        'type' => 'email.delivered',
        'created_at' => now()->toIso8601String(),
        'data' => [
            'email_id' => 'resend-message-123',
            'tags' => ['delivery_id' => (string) $delivery->id],
        ],
    ];

    postResendWebhook($this, $deliveredPayload, $secret)->assertOk();

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Delivered->value)
        ->and($delivery->delivered_at)->not->toBeNull();

    $olderFailurePayload = [
        'type' => 'email.failed',
        'created_at' => now()->subMinutes(2)->toIso8601String(),
        'data' => [
            'email_id' => 'resend-message-123',
            'reason' => 'An older provider event.',
        ],
    ];

    postResendWebhook($this, $olderFailurePayload, $secret)->assertOk();

    expect($delivery->refresh()->status)->toBe(EmailDeliveryStatus::Delivered->value);
});

test('Resend webhook rejects an invalid signature', function () {
    Config::set('resend.webhook.secret', 'whsec_'.base64_encode('correct-secret'));

    $this->postJson(route('resend.webhook'), [
        'type' => 'email.delivered',
        'created_at' => now()->toIso8601String(),
        'data' => ['email_id' => 'message-123'],
    ], [
        'svix-id' => 'event-123',
        'svix-timestamp' => (string) now()->timestamp,
        'svix-signature' => 'v1,invalid',
    ])->assertForbidden();
});

function resendWebhookSignature(string $payload, string $secret, string $messageId, int $timestamp): string
{
    $signature = hash_hmac('sha256', "{$messageId}.{$timestamp}.{$payload}", $secret);

    return 'v1,'.base64_encode(pack('H*', $signature));
}

function postResendWebhook(TestCase $testCase, array $payload, string $secret): TestResponse
{
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    $messageId = 'event-'.fake()->uuid();
    $timestamp = now()->timestamp;

    return $testCase->call('POST', route('resend.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_SVIX_ID' => $messageId,
        'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
        'HTTP_SVIX_SIGNATURE' => resendWebhookSignature($json, $secret, $messageId, $timestamp),
    ], $json);
}
