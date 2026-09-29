<?php

namespace App\Services;

use App\Models\DuePayment;
use App\Models\PaymentAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class PaystackService
{
    public const string NigerianBankCacheKey = 'paystack.banks.nigeria';

    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @return array<string, mixed>
     */
    public function initializeTransaction(DuePayment $payment, string $callbackUrl): array
    {
        $payment->loadMissing(['assignment.due.paymentAccount', 'paymentAccount', 'studentProfile.user']);
        $due = $payment->assignment->due;
        $studentProfile = $payment->studentProfile;
        $studentUser = $studentProfile->user;
        $email = $studentUser?->email;
        $paymentAccount = $payment->paymentAccount ?? $due->paymentAccount;

        if (! is_string($email) || $email === '') {
            throw new RuntimeException('This student needs an email address before payment can start.');
        }

        if (! ($paymentAccount instanceof PaymentAccount) || ! $paymentAccount->isActive()) {
            throw new RuntimeException('This due does not have an active payout account.');
        }

        $payload = [
            'email' => $email,
            'amount' => (string) $payment->amount_kobo,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'callback_url' => $callbackUrl,
            'channels' => config('services.paystack.channels'),
            'subaccount' => $paymentAccount->paystack_subaccount_code,
            'bearer' => $this->splitFeeBearer(),
            'metadata' => [
                'advisor_profile_id' => $payment->advisor_profile_id,
                'due_id' => $due->id,
                'payment_account_id' => $paymentAccount->id,
                'student_due_assignment_id' => $payment->student_due_assignment_id,
                'student_profile_id' => $payment->student_profile_id,
                'registration_number' => $studentProfile->registration_number,
                'custom_fields' => [
                    [
                        'display_name' => 'Due',
                        'variable_name' => 'due',
                        'value' => $due->title,
                    ],
                    [
                        'display_name' => 'Registration Number',
                        'variable_name' => 'registration_number',
                        'value' => $studentProfile->registration_number,
                    ],
                ],
            ],
        ];

        return $this->responseData(
            $this->client()->post('/transaction/initialize', $payload),
            'Paystack could not start this payment.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyTransaction(string $reference): array
    {
        return $this->responseData(
            $this->client()->get('/transaction/verify/'.rawurlencode($reference)),
            'Paystack could not verify this payment.',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBanks(string $country = 'nigeria'): array
    {
        $banks = [];
        $next = null;
        $page = 0;

        do {
            $query = [
                'country' => $country,
                'perPage' => 100,
                'use_cursor' => true,
            ];

            if (is_string($next) && $next !== '') {
                $query['next'] = $next;
            }

            $response = $this->client()->get('/bank', $query);
            $payload = $response->json();
            $data = $this->responseData($response, 'Paystack could not load Nigerian banks.');

            if (is_array($data)) {
                $banks = array_merge($banks, $data);
            }

            $next = Arr::get($payload, 'meta.next');
            $page++;
        } while (is_string($next) && $next !== '' && $page < 10);

        return $banks;
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function nigerianBankOptions(): array
    {
        $banks = Cache::remember(
            self::NigerianBankCacheKey,
            now()->addDay(),
            fn (): array => $this->bankOptionsForCountry('nigeria'),
        );

        return is_array($banks) ? $banks : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        return $this->responseData(
            $this->client()->get('/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]),
            'Paystack could not confirm this account number.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createSubaccount(
        string $businessName,
        string $bankCode,
        string $accountNumber,
        string $description,
    ): array {
        return $this->responseData(
            $this->client()->post('/subaccount', [
                'business_name' => $businessName,
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'percentage_charge' => 0,
                'description' => $description,
            ]),
            'Paystack could not create this payout account.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function updateSubaccount(
        string $subaccountCode,
        string $businessName,
        string $bankCode,
        string $accountNumber,
        string $description,
    ): array {
        return $this->responseData(
            $this->client()->put('/subaccount/'.rawurlencode($subaccountCode), [
                'business_name' => $businessName,
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'percentage_charge' => 0,
                'description' => $description,
                'active' => true,
            ]),
            'Paystack could not update this payout account.',
        );
    }

    public function validSignature(string $rawPayload, ?string $signature): bool
    {
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawPayload, $this->secretKey()), $signature);
    }

    private function client(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl())
            ->withToken($this->secretKey())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(
                2,
                250,
                fn (Throwable $throwable): bool => $throwable instanceof ConnectionException,
                throw: false,
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(Response $response, string $fallbackMessage): array
    {
        $payload = $response->json();
        $providerMessage = is_array($payload) ? Arr::get($payload, 'message') : null;

        if ($response->failed() || ! is_array($payload) || Arr::get($payload, 'status') !== true) {
            throw new PaystackException(
                $fallbackMessage,
                $response->status(),
                is_string($providerMessage) ? $providerMessage : null,
            );
        }

        $data = Arr::get($payload, 'data', []);

        if (! is_array($data)) {
            throw new PaystackException($fallbackMessage, $response->status());
        }

        return $data;
    }

    private function baseUrl(): string
    {
        $baseUrl = config('services.paystack.base_url');

        return is_string($baseUrl) && $baseUrl !== ''
            ? rtrim($baseUrl, '/')
            : 'https://api.paystack.co';
    }

    private function secretKey(): string
    {
        $secretKey = config('services.paystack.secret_key');

        if (! is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        return $secretKey;
    }

    private function splitFeeBearer(): string
    {
        $bearer = config('services.paystack.split_fee_bearer');

        return in_array($bearer, ['account', 'subaccount'], true) ? $bearer : 'subaccount';
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    private function bankOptionsForCountry(string $country): array
    {
        return collect($this->listBanks($country))
            ->map(function (array $bank): ?array {
                $code = Arr::get($bank, 'code');
                $name = Arr::get($bank, 'name');

                if ((! is_string($code) && ! is_numeric($code)) || ! is_string($name) || $name === '') {
                    return null;
                }

                return [
                    'code' => (string) $code,
                    'name' => $name,
                ];
            })
            ->filter()
            ->unique('code')
            ->sortBy('name')
            ->values()
            ->all();
    }
}
