<?php

use App\Jobs\SendCloudflareEmail;
use App\Models\EmailDelivery;
use App\Models\School;
use App\Models\User;
use App\Services\CloudflareEmailService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('access code email delivery is queued with the information needed by the worker', function () {
    Queue::fake();
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $delivery = EmailDelivery::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    app(CloudflareEmailService::class)->sendAccessCode($delivery, 'CPE-STAFF-123456');

    expect($delivery->refresh()->status)->toBe('queued')
        ->and($delivery->metadata['template'])->toBe('access_code')
        ->and($delivery->metadata['access_code'])->toBe('CPE-STAFF-123456');

    Queue::assertPushed(
        SendCloudflareEmail::class,
        fn (SendCloudflareEmail $job): bool => $job->delivery->is($delivery),
    );
});

test('Cloudflare delivery is idempotent after the provider accepts the message', function () {
    Config::set('services.cloudflare_email.endpoint', 'https://email.example.test/send');
    Config::set('services.cloudflare_email.token', 'cloudflare-test-token');
    Http::preventStrayRequests();
    Http::fake([
        'https://email.example.test/send' => Http::response(['id' => 'message-123']),
    ]);
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $delivery = EmailDelivery::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'status' => 'queued',
        'metadata' => [
            'template' => 'access_code',
            'access_code' => 'CPE-STAFF-123456',
        ],
    ]);
    $service = app(CloudflareEmailService::class);

    $service->deliver($delivery);
    $service->deliver($delivery->refresh());

    expect($delivery->refresh()->status)->toBe('sent')
        ->and($delivery->provider_message_id)->toBe('message-123')
        ->and($delivery->sent_at)->not->toBeNull();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->hasHeader(
        'Idempotency-Key',
        'email-delivery-'.$delivery->id,
    ));
});
