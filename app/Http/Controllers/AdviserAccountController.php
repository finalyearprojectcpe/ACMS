<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdviserPaymentAccountRequest;
use App\Http\Requests\UpdateAdviserProfileRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\DashboardAccess;
use App\Services\PaystackException;
use App\Services\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
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
        $description = sprintf('%s payout account for %s', $validated['label'], $advisor->user->displayName());

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

        return to_route('dashboard.section', ['section' => 'settings'])
            ->with('success', 'Payout account saved. New payments will use this account.');
    }

    public function updateProfile(UpdateAdviserProfileRequest $request): RedirectResponse
    {
        $user = $this->access->user($request);
        $validated = $request->validated();

        $changes = [
            'title' => $validated['title'],
            'name' => $validated['name'],
        ];

        if ($request->filled('password')) {
            $changes['password'] = $validated['password'];
        }

        $this->saveProfile($user, $changes, $request->file('profile_photo'));

        return to_route('dashboard.section', ['section' => 'settings'])
            ->with('success', 'Profile saved.');
    }

    public function updateStudentProfile(UpdateStudentProfileRequest $request): RedirectResponse
    {
        $student = $this->access->student($request);
        $user = $student->user;
        $validated = $request->validated();

        $changes = [];

        if ($request->filled('password')) {
            $changes['password'] = $validated['password'];
        }

        $this->saveProfile($user, $changes, $request->file('profile_photo'));

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', 'Profile saved.');
    }

    /** @param array<string, mixed> $changes */
    private function saveProfile(User $user, array $changes, ?UploadedFile $photo): void
    {
        $disk = User::profilePhotoDisk();
        $previousPhoto = $user->profile_photo_path;
        $newPhoto = $photo?->store('profile-photos', $disk);

        if ($photo !== null && ! is_string($newPhoto)) {
            throw new \RuntimeException('The profile photo could not be stored.');
        }

        if (is_string($newPhoto)) {
            $changes['profile_photo_path'] = $newPhoto;
        }

        try {
            DB::transaction(fn () => $user->forceFill($changes)->save());
        } catch (\Throwable $exception) {
            if (is_string($newPhoto)) {
                Storage::disk($disk)->delete($newPhoto);
            }

            throw $exception;
        }

        if (is_string($newPhoto) && is_string($previousPhoto) && $previousPhoto !== '') {
            Storage::disk($disk)->delete($previousPhoto);
        }
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
