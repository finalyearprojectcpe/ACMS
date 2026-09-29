<?php

use App\Models\AdvisorProfile;
use App\Models\PaymentAccount;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('an adviser can open the payout form and connect an account', function () {
    $advisor = AdvisorProfile::factory()->create();
    config()->set('services.paystack.secret_key', 'sk_test_example');
    config()->set('inertia.ssr.enabled', false);
    Cache::forget(PaystackService::NigerianBankCacheKey);
    Http::fake([
        '*/bank/resolve*' => Http::response(['status' => true, 'data' => ['account_name' => 'Example Adviser']]),
        '*/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_example']]),
        '*/bank*' => Http::response(['status' => true, 'data' => [['code' => '058', 'name' => 'Example Bank']]]),
    ]);
    Http::preventStrayRequests();

    $this->actingAs($advisor->user)
        ->get(route('dashboard.section', ['section' => 'settings']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.adviserPaymentSettings.accounts', 0));

    $this->actingAs($advisor->user)
        ->get(route('dashboard.payments.account.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.adviserPaymentAccountForm.bankOptions.0.code', '058'));

    $this->post(route('dashboard.payments.account.store'), [
        'label' => 'Student dues',
        'business_name' => 'Student dues',
        'bank_code' => '058',
        'account_number' => '0123456789',
    ])->assertRedirect(route('dashboard.section', ['section' => 'settings']));

    expect(PaymentAccount::query()->where('advisor_profile_id', $advisor->id)->first())
        ->not->toBeNull()
        ->bank_code->toBe('058')
        ->account_name->toBe('Example Adviser');
});
