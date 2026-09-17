<?php

namespace App\Http\Controllers;

use App\AccountType;
use App\Http\Requests\StoreAdviserPaymentAccountRequest;
use App\Http\Requests\UpdateAdviserPasswordRequest;
use App\Http\Requests\UpdateProfilePhotoRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\DashboardAccess;
use App\Services\PaystackException;
use App\Services\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdviserAccountController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function storePaymentAccount(
        StoreAdviserPaymentAccountRequest $request,
        PaystackService $paystack,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $validated = $request->validated();
        $bank = $this->paystackBankFromCode($paystack, $validated['bank_code']);
        $current = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);
        $description = sprintf('%s payout account for %s', $validated['label'], $advisor->user->name);

        try {
            $resolved = $paystack->resolveAccount($validated['account_number'], $validated['bank_code']);
            $subaccount = $current instanceof PaymentAccount
                && is_string($current->paystack_subaccount_code)
                && $current->paystack_subaccount_code !== ''
                && data_get($current->provider_payload, 'demo') !== true
                    ? $this->updateOrReplaceSubaccount($paystack, $current, $validated, $description)
                    : $paystack->createSubaccount(
                        $validated['business_name'],
                        $validated['bank_code'],
                        $validated['account_number'],
                        $description,
                    );
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'account_number' => 'Paystack could not confirm this account. Check the bank and account number, then try again.',
            ]);
        }

        $accountName = Arr::get($resolved, 'account_name');
        $subaccountCode = Arr::get($subaccount, 'subaccount_code');

        if (! is_string($accountName)
            || $accountName === ''
            || ! is_string($subaccountCode)
            || $subaccountCode === '') {
            throw ValidationException::withMessages([
                'account_number' => 'Paystack did not return enough account details. Check the account and try again.',
            ]);
        }

        DB::transaction(function () use ($advisor, $current, $validated, $bank, $accountName, $subaccountCode, $subaccount): void {
            $account = $current ?? new PaymentAccount;
            $account->forceFill([
                'advisor_profile_id' => $advisor->id,
                'provider' => PaymentAccount::ProviderPaystack,
                'label' => $validated['label'],
                'business_name' => $validated['business_name'],
                'bank_code' => $validated['bank_code'],
                'bank_name' => $bank['name'],
                'account_number' => $validated['account_number'],
                'account_number_last4' => Str::of($validated['account_number'])->substr(-4)->toString(),
                'account_name' => $accountName,
                'paystack_subaccount_code' => $subaccountCode,
                'status' => PaymentAccount::StatusActive,
                'verified_at' => now(),
                'activated_at' => $account->activated_at ?? now(),
                'disabled_at' => null,
                'provider_payload' => Arr::only($subaccount, [
                    'id',
                    'subaccount_code',
                    'domain',
                    'integration',
                    'managed_by_integration',
                    'active',
                    'is_verified',
                    'currency',
                    'createdAt',
                    'updatedAt',
                ]),
            ])->save();
            Due::query()
                ->where('advisor_profile_id', $advisor->id)
                ->update(['payment_account_id' => $account->id]);
            DuePayment::query()
                ->where('advisor_profile_id', $advisor->id)
                ->where('status', DuePayment::StatusInitialized)
                ->update([
                    'status' => DuePayment::StatusAbandoned,
                    'active_marker' => null,
                    'gateway_response' => 'Checkout was replaced after the payout account changed.',
                ]);
        });

        return to_route('dashboard.section', ['section' => 'payment-settings'])
            ->with('success', 'Payout account saved. New payments will use this account.');
    }

    public function updatePassword(UpdateAdviserPasswordRequest $request): RedirectResponse
    {
        $user = $this->access->user($request);
        abort_unless($user->account_type === AccountType::Adviser, 403);
        $user->forceFill(['password' => $request->validated('password')])->save();

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', 'Password changed.');
    }

    public function updateStudentProfile(UpdateStudentProfileRequest $request): RedirectResponse
    {
        $student = $this->access->student($request);
        $validated = $request->validated();
        $student->user->forceFill([
            'name' => Str::squish($validated['surname'].' '.$validated['other_names']),
        ])->save();

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', 'Profile saved.');
    }

    public function updateProfilePhoto(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $user = $this->access->user($request);
        $disk = User::profilePhotoDisk();
        $previous = $user->profile_photo_path;
        $path = $request->file('profile_photo')->store('profile-photos', $disk);
        $user->forceFill(['profile_photo_path' => $path])->save();

        if (is_string($previous) && $previous !== '') {
            Storage::disk($disk)->delete($previous);
        }

        return back()->with('success', 'Profile photo updated.');
    }

    /** @return array<string, mixed> */
    private function updateOrReplaceSubaccount(
        PaystackService $paystack,
        PaymentAccount $account,
        array $validated,
        string $description,
    ): array {
        try {
            return $paystack->updateSubaccount(
                (string) $account->paystack_subaccount_code,
                $validated['business_name'],
                $validated['bank_code'],
                $validated['account_number'],
                $description,
            );
        } catch (PaystackException $exception) {
            if (! $exception->isInvalidSubaccount()) {
                throw $exception;
            }

            return $paystack->createSubaccount(
                $validated['business_name'],
                $validated['bank_code'],
                $validated['account_number'],
                $description,
            );
        }
    }

    /** @return array{code: string, name: string} */
    private function paystackBankFromCode(PaystackService $paystack, string $bankCode): array
    {
        try {
            $bank = collect($paystack->nigerianBankOptions())->firstWhere('code', $bankCode);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'bank_code' => 'Paystack could not load the supported banks right now. Try again shortly.',
            ]);
        }

        if (! is_array($bank)) {
            throw ValidationException::withMessages([
                'bank_code' => 'Select a bank from the Paystack bank list.',
            ]);
        }

        return ['code' => (string) $bank['code'], 'name' => (string) $bank['name']];
    }
}
