<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaffPaymentAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->account_type === AccountType::Staff
            && $user->staffProfile !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
            'label' => ['required', 'string', 'max:80'],
            'business_name' => ['required', 'string', 'max:120'],
            'bank_code' => ['required', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['required', 'digits_between:10,12'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Name this payout account so it is easy to recognize later.',
            'business_name.required' => 'Enter the name Paystack should show for this payout account.',
            'bank_code.required' => 'Select the bank that owns this account.',
            'account_number.required' => 'Enter the account number that should receive dues.',
            'account_number.digits_between' => 'Enter a valid Nigerian account number.',
        ];
    }
}
